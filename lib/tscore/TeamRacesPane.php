<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package tscore
 */

/**
 * Page for editing races when using team scoring. These team races
 * require not just a number, but also the two teams from the set of
 * teams which will be participating. Note that this pane will
 * automatically allocate 3 divisions for the regatta.
 *
 * Each race must also belong to a particular "round". In a particular
 * round, each team races against every other team in a round robin.
 * It would be useful to have the user choose the teams that will
 * participate in a given round and have the program create the
 * pairings automatically. Then, the user has the option to add/remove
 * or reorder the pairings as needed.
 *
 * @author Dayan Paez
 * @version 2012-03-05
 */
class TeamRacesPane extends AbstractPane {

  const SIMPLE = 'simple';
  const COPY = 'copy';
  const COMPLETION = 'completion';

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Create Round", $user, $reg);
    if ($reg->scoring != Regatta::SCORING_TEAM)
      throw new InvalidArgumentException("TeamRacesPane only available for team race regattas.");
  }

  /**
   * Fills out the pane, allowing the user to add up to 10 races at a
   * time, or edit any one of the previous races
   *
   * @param Array $args (ignored)
   */
  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // New round?
    // ------------------------------------------------------------
    if (isset($args['new-round'])) {
      try {
        $this->fillNewRound($args);
        return;
      } catch (SoterException $e) {
        Session::pa(new PA($e->getMessage(), PA::E));
      }
    }

    $rounds = $this->REGATTA->getRounds();
    $master_rounds = array();
    foreach ($rounds as $round) {
      if (count($round->getMasters()) == 0)
        $master_rounds[] = $round;
    }

    // ------------------------------------------------------------
    // Create from previous
    // ------------------------------------------------------------
    if (isset($args['create-round']) && $args['create-round'] == self::COPY && count($rounds) > 0) {
      $this->PAGE->addContent($p = new XPort("Create from existing round"));
      $p->add($f = $this->createForm());
      $f->add(new XP(array(), "Create a new round by copying an existing round's races."));
      $f->add(new FItem("Round label:", new XTextInput('title', "Round " . (count($rounds) + 1))));
      $f->add(new FItem("Previous round:", XSelect::fromDBM('template', $rounds)));
      $f->add($fi = new FItem("Swap teams:", new XCheckboxInput('swap', 1, array('id'=>'chk-swap'))));
      $fi->add(new XLabel('chk-swap', "Reverse the teams in each race."));
      $f->add($xp = new XSubmitP('create-from-existing', "Add round"));
      $xp->add(" ");
      $xp->add(new XA($this->link('races'), "Cancel"));
      return;
    }

    // ------------------------------------------------------------
    // Create "completion" (slave) round, if there are at least two
    // non-slave rounds available
    // ------------------------------------------------------------
    if (isset($args['create-round']) && $args['create-round'] == self::COMPLETION && count($rounds) > 0 && count($master_rounds) > 1) {

      $this->PAGE->addContent($p = new XPort("Create a round to complete previous round(s)"));
      $p->add($form = $this->createForm());
      $form->add(new XP(array(),
                        array("Use this form to create a new round where some of the races come from previously existing round(s). Only as many races as needed to complete a round robin will be created. For each round to \"carry-over from\", indicate the teams that advance from that round. Note that a team may only be imported from one round.")));
      $form->add(new FItem("Round label:", new XTextInput('title', "Round " . (count($rounds) + 1))));
      $form->add(new FItem("Boat:", XSelect::fromArray('boat', $this->getBoatOptions())));

      foreach ($master_rounds as $round) {
        if (count($round->getMasters()) > 0)
          continue;

        $form->add(new FItem($round . ":", $ul = new XUl(array('class'=>'inline-list'))));
        $id = sprintf('teams[%d][]', $round->id);
        foreach ($this->REGATTA->getTeamsInRound($round) as $team) {
          $cid = sprintf('round-%d-team-%d', $round->id, $team->id);
          $ul->add(new XLi(array(new XCheckboxInput($id, $team->id, array('id'=>$cid)),
                                 new XLabel($cid, $team))));
        }
      }
      $form->add($xp = new XSubmitP('add-slave-round', "Add round"));
      $xp->add(" ");
      $xp->add(new XA($this->link('races'), "Cancel"));
      return;
    }

    // ------------------------------------------------------------
    // Regular process
    // ------------------------------------------------------------
    $this->PAGE->head->add(new LinkCSS('/inc/css/round.css'));
    $ROUND = Session::g('round');

    // Calculate step
    $MAX_STEP = 0;
    if ($ROUND === null && count($rounds) == 0) {
      $ROUND = new Round();
      Session::s('round', $ROUND);
    }

    $team_ids = Session::g('round_teams');
    if ($ROUND !== null) {
      $MAX_STEP = 1;
      if ($ROUND->num_teams !== null) {
        $MAX_STEP = 2;
        if ($ROUND->race_order !== null) {
          $MAX_STEP = 3;
          if ($ROUND->rotation !== null) {
            $MAX_STEP = 4;
            if ($team_ids !== null)
              $MAX_STEP = 5;
          }
        }
      }
    }
    $STEP = DB::$V->incInt($args, 'step', 0, $MAX_STEP + 1, $MAX_STEP);

    // ------------------------------------------------------------
    // Progress report
    // ------------------------------------------------------------
    $this->PAGE->addContent($f = $this->createForm());
    $f->add($prog = new XP(array('id'=>'progressdiv')));
    $this->fillProgress($prog, $MAX_STEP, $STEP);

    // ------------------------------------------------------------
    // Step 0: Offer choice
    // ------------------------------------------------------------
    if ($STEP == 0) {
      $this->PAGE->addContent($p = new XPort("Add round"));
      $p->add(new XP(array(), "To get started, choose the  kind of round you would like to add."));
      $p->add($f = $this->createForm());

      $opts = array(self::SIMPLE => "Standard round robin");
      if (count($rounds) > 0)
        $opts[self::COPY] = "Using existing round as template";
      if (count($master_rounds) > 1)
        $opts[self::COMPLETION] = "Completion round";
      $f->add(new FItem("Add round:", XSelect::fromArray('create-round', $opts)));
      $f->add(new XSubmitP('go', "Next →"));

      $this->PAGE->addContent($p = new XPort("Explanation"));
      $p->add($ul = new XUl(array(),
                            array(new XLi(array(new XStrong("Standard round robin"), " refers to a regular round robin whose races do not depend on any other round. This is the default choice.")))));
      if (isset($opts[self::COPY]))
        $ul->add(new XLi(array(new XStrong("Using existing round as template"), " will create a round by copying races and teams from a previously-existing round.")));
      if (isset($opts[self::COMPLETION]))
        $ul->add(new XLi(array(new XStrong("Completion round"), " refers to a round where some of the races come from previously existing round(s).")));
      return;

    }

    $boats = $this->getBoatOptions();
    
    // ------------------------------------------------------------
    // Step 1: Settings
    // ------------------------------------------------------------
    $divisions = $this->REGATTA->getDivisions();
    $num_divs = count($divisions);
    $group_size = 2 * $num_divs;

    if ($STEP == 1) {
      if ($ROUND->title === null)
        $ROUND->title = sprintf("Round %d", count($rounds) + 1);
      if ($ROUND->num_teams === null)
        $ROUND->num_teams = count($this->REGATTA->getTeams());
      if ($ROUND->num_boats === null)
        $ROUND->num_boats = $group_size * 3;
      $boat = null;
      if ($ROUND->boat !== null)
        $boat = $ROUND->boat->id;

      $this->PAGE->addContent($p = new XPort("New round settings"));
      $p->add($form = $this->createForm());
      $form->add(new FItem("Round name:", new XTextInput('title', $ROUND->title)));
      $form->add(new FItem("Number of teams:", new XTextInput('num_teams', $ROUND->num_teams)));

      $form->add(new FItem("Number of boats:", new XInput('number', 'num_boats', $ROUND->num_boats, array('min'=>$group_size, 'step'=>$group_size))));
      $form->add(new FItem("Rotation frequency:", XSelect::fromArray('rotation_frequency', Race_Order::getFrequencyTypes())));
      $form->add(new FItem("Boat:", XSelect::fromArray('boat', $boats, $boat)));
      $form->add($p = new XSubmitP('create-settings', "Next →"));
      return;
    }

    // ------------------------------------------------------------
    // Step 2: Race order
    // ------------------------------------------------------------
    if ($STEP == 2) {
      $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tablesort.js'));
      $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/toggle-tablesort.js'));
      $this->PAGE->addContent($p = new XPort("Race orders"));
      $p->add($form = $this->createForm());
      $form->set('id', 'edit-races-form');

      $order = null;
      $message = null;
      if ($ROUND->race_order !== null) {
        $order = new Race_Order();
        $order->template = $ROUND->race_order;
        $message = "The saved race order is shown below.";
      }
      else {
        $order = DB::getRaceOrder($num_divs, $ROUND->num_teams, $ROUND->num_boats, $ROUND->rotation_frequency);
        $message = "A race order template has been automatically chosen below.";
      }
      if ($order === null) {
        // Create basic order
        $template = array();
        for ($i = 0; $i < $ROUND->num_teams - 1; $i++) {
          for ($j = $i + 1; $j < $ROUND->num_teams; $j++) {
            $template[] = sprintf("%d-%d", ($i + 1), ($j + 1));
          }
        }
        $order = new Race_Order();
        $order->template = $template;
        $message = new XStrong("Please set the race order below.");
      }

      $form->add(new XNoScript("To reorder the races, indicate the relative desired order in the first cell."));
      $form->add(new XScript('text/javascript', null, 'var f = document.getElementById("edit-races-form"); var p = document.createElement("p"); p.appendChild(document.createTextNode("To reorder the races, move the rows below by clicking and dragging on the first cell (\"#\") of that row.")); f.appendChild(p);'));
      $form->add(new XP(array(), $message));
      $form->add(new XNoScript(array(new XP(array(),
                                            array(new XStrong("Important:"), " check the edit column if you wish to edit that race. The race will not be updated regardless of changes made otherwise.")))));
      $header = array("Order", "#");
      $header[] = "First team";
      $header[] = "← Swap →";
      $header[] = "Second team";
      $form->add($tab = new XQuickTable(array('id'=>'divtable', 'class'=>'teamtable'), $header));
      for ($i = 0; $i < count($order->template); $i++) {
        $pair = $order->getPair($i);
        $tab->addRow(array(new XTextInput('order[]', ($i + 1), array('size'=>2)),
                           new XTD(array('class'=>'drag'), ($i + 1)),
                           new XTD(array(),
                                   array(new XEm(sprintf("Team %d", $pair[0])),
                                         new XHiddenInput('team1[]', $pair[0]))),
                           new XCheckboxInput('swap[]', $i),
                           new XTD(array(),
                                   array(new XEm(sprintf("Team %d", $pair[1])),
                                         new XHiddenInput('team2[]', $pair[1])))),
                     array('class'=>'sortable'));
      }
      $form->add(new XSubmitP('create-order', "Next →"));
      return;
    }

    // ------------------------------------------------------------
    // Step 3: Sails
    // ------------------------------------------------------------
    if ($STEP == 3) {
      $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/tr-rot.js')));
      $this->PAGE->addContent($p = new XPort("Sail numbers and colors"));
      $p->add($form = $this->createForm());
      $form->set('id', 'tr-rotation-form');

      $COLORS = array(
                      "#eee" => "White",
                      "#ccc" => "Light Grey",
                      "#666" => "Grey",
                      "#000" => "Black",
                      "#884B2A" => "Brown",
                      "#f80" => "Orange",
                      "#f00" => "Red",
                      "#fcc" => "Pink",
                      "#add8e6" => "Light Blue",
                      "#00f" => "Blue",
                      "#808" => "Purple",
                      "#0f0" => "Lime Green",
                      "#080" => "Green",
                      "#ff0" => "Yellow"
                      );

      $flight = $ROUND->num_boats;
      $rotation = $ROUND->rotation;
      if ($rotation === null) {
        // Find another rotation for this number of boats
        for ($i = count($rounds) - 1; $i >= 0; $i--) {
          $other = $rounds[$i];
          if ($other->num_boats == $ROUND->num_boats && $other->rotation !== null) {
            $rotation = $other->rotation;
            break;
          }
        }
      }

      $form->add(new XP(array(), "Assign the sail numbers using the table below. If applicable, choose the color that goes with the sail. This color will be displayed in the \"Rotations\" dialog."));
      if ($ROUND->rotation_frequency == Race_Order::FREQUENCY_FREQUENT ||
          $ROUND->rotation_frequency == Race_Order::FREQUENCY_INFREQUENT) {

        // Prefill
        if ($rotation === null) {
          $rotation = new TeamRotation();
          $s = array();
          $c = array();
          for ($i = 0; $i < $flight; $i++) {
            $s[] = ($i + 1);
            $c[] = "";
          }
          $rotation->sails = $s;
          $rotation->colors = $c;
        }

        $form->add(new XP(array(), array("The flight size for this rotation is ", new XStrong($flight / $group_size), " races.")));

        $form->add(new XTable(array('class'=>'tr-rotation-sails'),
                              array(new XTHead(array(),
                                               array(new XTR(array(),
                                                             array(new XTH(array(), "#"),
                                                                   new XTH(array(), "Team A"),
                                                                   new XTH(array(), "Team B"))))),
                                    $bod = new XTBody())));

        $sailIndex = 0;
        for ($race_num = 0; $race_num < $flight / $group_size; $race_num++) {
          $bod->add($row = new XTR(array()));
          $row->add(new XTH(array(), sprintf("Race %d", ($race_num + 1))));

          // Team A, then Team B
          for ($teamIndex = 0; $teamIndex < 2; $teamIndex++) {
            $row->add(new XTD(array(), new XTable(array('class'=>'sail-list'), array($tab = new XTBody()))));
            for ($i = 0; $i < $num_divs; $i++) {
              $sail = $rotation->sails[$sailIndex];
              $color = $rotation->colors[$sailIndex];

              $tab->add(new XTR(array(),
                                array(new XTD(array(), new XTextInput('sails[]', $sail, array('size'=>5, 'tabindex'=>($sailIndex + 1)))),
                                      new XTD(array('title'=>"Optional"), $sel = new XSelect('colors[]')))));
              $sel->set('class', 'color-chooser');
              $sel->set('tabindex', ($sailIndex + 1 + $flight));
              $sel->add(new XOption("", array(), "[None]"));
              foreach ($COLORS as $code => $title) {
                $attrs = array('style'=>sprintf('background:%1$s;color:%1$s;', $code));
                $sel->add($opt1 = new XOption($code, $attrs, $title));
                if ($code == $color)
                  $opt->set('selected', 'selected');
              }

              $sailIndex++;
            }
          }
        }
      }
      else {
        // Prefill
        if ($rotation === null) {
          $rotation = new TeamRotation();
          $s = array();
          $c = array();
          for ($i = 0; $i < $flight; $i++) {
            $s[] = ($i + 1);
            $c[] = "";
          }
          $rotation->sails = $s;
          $rotation->colors = $c;
        }

        // No rotation frequency: display an entry PER team
        $form->add($tab = new XQuickTable(array('class'=>'tr-rotation-sails'),
                                          array("Team #", "Sail #", "Color")));

        for ($i = 0; $i < $rotation->count(); $i++) {
          $row = array();
          if ($i % $num_divs == 0)
            $row[] = new XTH(array('rowspan'=>$num_divs), sprintf("Team %d", floor($i / $num_divs)));

          $sail = $rotation->sails[$i];
          $color = $rotation->colors[$i];

          $sel = new XSelect('colors[]', array('class'=>'color-chooser', 'tabindex'=>($i + 1 + $rotation->count())));
          $row[] = new XTextInput('sails[]', $sail, array('size'=>5, 'tabindex'=>($i + 1)));
          $row[] = $sel;

          $sel->add(new XOption("", array(), "[None]"));
          foreach ($COLORS as $code => $title) {
            $attrs = array('style'=>sprintf('background:%1$s;color:%1$s;', $code));
            $sel->add($opt = new XOption($code, $attrs, $title));

            if ($code == $color)
              $opt->set('selected', 'selected');
          }

          $tab->addRow($row);
        }

      }
      $form->add(new XSubmitP('create-sails', "Next →"));
      return;
    }

    // ------------------------------------------------------------
    // Step 4: Teams?
    // ------------------------------------------------------------
    if ($STEP == 4) {
      $ids = Session::g('round_teams');
      if ($ids === null)
        $ids = array();

      $this->PAGE->addContent($p = new XPort("Teams (optional)"));
      $p->add($form = $this->createForm());
      $form->add(new XP(array(),
                        array(new XStrong("(Optional)"),
                              " Specify and seed the teams that will participate in this round. You may specify the teams at a later time. Note that all teams must be entered at the same time.")));
      $form->add(new XP(array(), sprintf("Place numbers 1-%d next to the teams to be included in this round.", $ROUND->num_teams)));

      $form->add($ul = new XUl(array('id'=>'teams-list')));
      $num = 1;
      foreach ($this->REGATTA->getTeams() as $team) {
        $id = 'team-'.$team->id;
        $order = array_search($team->id, $ids);
        $order = ($order === false) ? "" : $order + 1;
        $ul->add(new XLi(array(new XHiddenInput('team[]', $team->id),
                               new XTextInput('order[]', $order, array('id'=>$id)),
                               new XLabel($id, $team,
                                          array('onclick'=>sprintf('addTeamToRound("%s");', $id))))));
      }
      $form->add(new XSubmitP('create-teams', "Next →"));
      return;
    }

    // ------------------------------------------------------------
    // Step 5: Review
    // ------------------------------------------------------------
    if ($STEP == 5) {
      $this->PAGE->addContent($p = new XPort("Review"));
      $p->add($form = $this->createForm());
      $form->add(new XP(array(), "Verify that all the information is correct. Click \"Create\" to create the round, or use the progress bar above to go back to a different step."));

      $teams = array();
      $ids = Session::g('round_teams');
      if ($ids !== null) {
        foreach ($ids as $id) {
          $team = $this->REGATTA->getTeam($id);
          if ($team !== null)
            $teams[] = $team;
        }
      }
      for ($i = count($teams); $i < $ROUND->num_teams; $i++) {
        $teams[] = new XEm(sprintf("Team %d", ($i + 1)), array('class'=>'no-team'));
      }

      $sails = $ROUND->rotation->assignSails($ROUND, $teams, $divisions, $ROUND->rotation_frequency);
      $tab = new XTable(array('class'=>'tr-rotation-table'),
                        array(new XTHead(array(),
                                         array(new XTR(array(),
                                                       array(new XTH(array(), "#"),
                                                             new XTH(array('colspan'=>2), "Team 1"),
                                                             new XTH(array('colspan'=>count($divisions)), "Sails"),
                                                             new XTH(array(), ""),
                                                             new XTH(array('colspan'=>count($divisions)), "Sails"),
                                                             new XTH(array('colspan'=>2), "Team 2"))))),
                              $body = new XTBody()));

      $flight = $ROUND->num_boats / $group_size;
      for ($i = 0; $i < count($ROUND->race_order); $i++) {
        // spacer
        if ($flight > 0 && $i % $flight == 0) {
          $body->add(new XTR(array('class'=>'tr-flight'), array(new XTD(array('colspan' => 8 + 2 * count($divisions)), sprintf("Flight %d", ($i / $flight + 1))))));
        }

        $pair = $ROUND->getRaceOrderPair($i);
        $team1 = $teams[$pair[0] - 1];
        $team2 = $teams[$pair[1] - 1];

        // Burgees
        $burg1 = "";
        $burg2 = "";
        if ($team1 instanceof Team)
          $burg1 = $team1->school->drawSmallBurgee("");
        if ($team2 instanceof Team)
          $burg2 = $team2->school->drawSmallBurgee("");

        $body->add($row = new XTR(array(), array(new XTD(array(), ($i + 1)),
                                                 new XTD(array('class'=>'team1'), $burg1),
                                                 new XTD(array('class'=>'team1'), $team1))));
        // first team
        foreach ($divisions as $div) {
          $sail = null;
          if (isset($sails[$i]))
            $sail = $sails[$i][$pair[0]][(string)$div];
          $row->add($s = new XTD(array('class'=>'team1 tr-sail'), $sail));
          if ($sail !== null && $sail->color !== null)
            $s->set('style', sprintf('background:%s;', $sail->color));
        }

        $row->add(new XTD(array('class'=>'vscell'), "vs"));

        // second team
        foreach ($divisions as $div) {
          $sail = null;
          if (isset($sails[$i]))
            $sail = $sails[$i][$pair[1]][(string)$div];
          $row->add($s = new XTD(array('class'=>'team2 tr-sail'), $sail));
          if ($sail !== null && $sail->color !== null)
            $s->set('style', sprintf('background:%s;', $sail->color));
        }

        $row->add(new XTD(array('class'=>'team2'), $team2));
        $row->add(new XTD(array('class'=>'team2'), $burg2));
      }

      $form->add($tab);

      // Include all information at once
      $form->add(new XHiddenInput('title', $ROUND->title));
      $form->add(new XHiddenInput('num_teams', $ROUND->num_teams));
      $form->add(new XHiddenInput('num_boats', $ROUND->num_boats));
      $form->add(new XHiddenInput('rotation_frequency', $ROUND->rotation_frequency));
      $form->add(new XHiddenInput('boat', $ROUND->boat->id));
      for ($i = 0; $i < count($ROUND->race_order); $i++) {
        $pair = $ROUND->getRaceOrderPair($i);
        $form->add(new XHiddenInput('team1[]', $pair[0]));
        $form->add(new XHiddenInput('team2[]', $pair[1]));
      }
      $rotation = $ROUND->rotation;
      $num_divs = count($divisions);
      if ($rotation != null) {
        foreach ($rotation->sails as $i => $sail) {
          $form->add(new XHiddenInput('sails[]', $sail));
          $form->add(new XHiddenInput('colors[]', $rotation->colors[$i]));
        }
      }
      foreach ($teams as $team) {
        if ($team instanceof Team)
          $form->add(new XHiddenInput('team[]', $team->id));
      }
      $form->add(new XSubmitP('create', "Create round"));
    }
  }

  private function fillProgress(XP $prog, $max, $step) {
    $steps = array("Round Type",
                   "Settings",
                   "Race Order",
                   "Sail # and Colors",
                   "Teams",
                   "Review");
    for ($i = 0; $i < $max + 1; $i++) {
      $prog->add($span = new XSpan(new XA($this->link('races', array('step' => $i)), $steps[$i])));
      if ($i == $step)
        $span->set('class', 'current');
      else
        $span->set('class', 'completed');
    }
    for ($i = $max + 1; $i < count($steps); $i++)
      $prog->add(new XSpan($steps[$i]));
  }

  private function getBoatOptions() {
    $boats = DB::getBoats();
    $boatOptions = array();
    foreach ($boats as $boat)
      $boatOptions[$boat->id] = $boat->name;
    return $boatOptions;
  }

  private function fillNewRound(Array $args) {
    $teams = $this->REGATTA->getTeams();
    $num_teams = DB::$V->reqInt($args, 'num_teams', 2, count($teams) + 1, "Invalid number of teams specified.");

    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/addTeamToRound.js'));
    $this->PAGE->addContent(new XP(array(), new XA($this->link('races'), "← Cancel")));
    $this->PAGE->addContent($p = new XPort("Create a new round"));
    
    $p->add($form = $this->createForm());
    $form->add(new XHiddenInput('num_teams', $num_teams));
    $form->add(new FItem("Round label:", new XTextInput('title', "Round " . (count($this->REGATTA->getRounds()) + 1))));

    $form->add(new FItem("Boat:", XSelect::fromArray('boat', $this->getBoatOptions())));

    // ------------------------------------------------------------
    // Teams
    // ------------------------------------------------------------
    $form->add(new XH4("Seeding order"));
    $form->add(new XP(array(), 
                      array("Choose the seeding order for the round by placing incrementing numbers next to the team name. ",
                            new XStrong(sprintf("Note: a total of %d teams must be chosen.", $num_teams)),
                            " If a team is not participating in the round, then leave it blank. This seeding order will be used to generate the race order for the round.")));

    $form->add($ul = new XUl(array('id'=>'teams-list')));
    $num = 1;
    foreach ($teams as $team) {
      $id = 'team-'.$team->id;
      $ul->add(new XLi(array(new XHiddenInput('team[]', $team->id),
                             new XTextInput('order[]', "", array('id'=>$id)),
                             new XLabel($id, $team,
                                        array('onclick'=>sprintf('addTeamToRound("%s");', $id))))));
    }

    // ------------------------------------------------------------
    // Race order
    // ------------------------------------------------------------
    $num_divs = count($this->REGATTA->getDivisions());
    $templates = DB::getRaceOrders($num_teams, $num_divs);
    $form->add(new XH4("Race order"));
    if (count($templates) == 0)
      $form->add(new XP(array('class'=>'warning'), "There are no templates in the system for this number of teams. As such, a standard race order will be applied, which you may manually alter later."));
    else {
      $frequencies = Race_Order::getFrequencyTypes();
      $form->add($tab = new XQuickTable(array('class'=>'tr-race-order-list'), array("", "# of boats", "Boat rotation", "Description")));
      foreach ($templates as $i => $template) {
        $id = 'inp-' . $template->id;
        $tab->addRow(array($ri = new XRadioInput('template', $template->id, array('id'=>$id)),
                           new XLabel($id, $template->num_boats),
                           new XLabel($id, $frequencies[$template->frequency]),
                           new XLabel($id, $template->description)),
                     array('class' => 'row'.($i % 2)));
      }
      if (count($templates) == 1)
        $ri->set('checked', 'checked');
      $tab->addRow(array(new XRadioInput('template', '', array('id'=>'inp-no-template')),
                         new XLabel('inp-no-template', "N/A"),
                         new XLabel('inp-no-template', "N/A"),
                         new XLabel('inp-no-template', "Group all of the first team's races, followed by those of the second team, etc. Use as a last resort.")),
                   array('class' => 'row'.(++$i % 2)));
    }

    $form->add(new XSubmitP('add-round', "Add round"));
  }

  /**
   * Processes new races and edits to existing races
   */
  public function process(Array $args) {
    $ROUND = Session::g('round');

    $rounds = array();
    $master_rounds = array();
    foreach ($this->REGATTA->getRounds() as $round) {
      $rounds[$round->id] = $round;
      if (count($round->getMasters()) == 0)
        $master_rounds[] = $round;
    }

    // ------------------------------------------------------------
    // Step 0: Round type
    // ------------------------------------------------------------
    if (isset($args['create-round'])) {
      // @TODO: self::COPY
      // @TODO: self::COMPLETION???

      if ($args['create-round'] == self::SIMPLE) {
        $ROUND = new Round();
        Session::s('round', $ROUND);
        $this->redirect('races');
        return;
      }
      throw new SoterException("Unknown round type provided.");
    }

    $divisions = $this->REGATTA->getDivisions();
    $group_size = 2 * count($divisions);

    // ------------------------------------------------------------
    // Step 1: settings
    // ------------------------------------------------------------
    if (isset($args['create-settings'])) {
      if ($ROUND === null)
        throw new SoterException("Order error: no round to work with.");

      $this->processStep1($args, $ROUND, $rounds, $divisions);
      $this->redirect('races');
      return;
    }

    // ------------------------------------------------------------
    // Step 2: race order
    // ------------------------------------------------------------
    if (isset($args['create-order'])) {
      if ($ROUND === null)
        throw new SoterException("Order error: no round to work with.");
      if ($ROUND->num_teams === null)
        throw new SoterException("Order error: number of teams unknown.");

      $this->processStep2($args, $ROUND);
      $this->redirect('races');
      return;
    }

    // ------------------------------------------------------------
    // Step 3: Sails
    // ------------------------------------------------------------
    if (isset($args['create-sails'])) {
      if ($ROUND === null)
        throw new SoterException("Order error: no round to work with.");
      if ($ROUND->num_boats === null)
        throw new SoterException("Order error: number of teams unknown.");

      $this->processStep3($args, $ROUND, $divisions);
      $this->redirect('races');
      return;
    }

    // ------------------------------------------------------------
    // Step 4: Teams
    // ------------------------------------------------------------
    if (isset($args['create-teams'])) {
      if ($ROUND === null)
        throw new SoterException("Order error: no round to work with.");
      if ($ROUND->num_teams === null)
        throw new SoterException("Order error: number of teams unknown.");
      if ($ROUND->race_order === null)
        throw new SoterException("Order error: race order not known.");
      if ($ROUND->rotation === null)
        throw new SoterException("Order error: no rotation found.");

      $teams = $this->processStep4($args, $ROUND);
      Session::s('round_teams', array_keys($teams));
      $this->redirect('races');
      return;
    }

    // ------------------------------------------------------------
    // Create round
    // ------------------------------------------------------------
    if (isset($args['create'])) {
      $round = new Round();
      $round->relative_order = count($rounds) + 1;
      $this->processStep1($args, $round, $rounds, $divisions);
      $this->processStep2($args, $round);
      $this->processStep3($args, $round, $divisions);
      $teams = $this->processStep4($args, $round);
      $teams = array_values($teams);

      $round->regatta = $this->REGATTA;
      $message = "Created new empty round.";
      DB::set($round);
      if (count($teams) > 0) {
        $racenum = $this->calculateNextRaceNumber($round);

        // Actually create the races
        $sails = array();
        if ($round->rotation !== null)
          $sails = $round->rotation->assignSails($round, $teams, $divisions, $round->rotation_frequency);
        $new_races = array();
        $new_sails = array();
        for ($i = 0; $i < count($round->race_order); $i++) {
          $racenum++;
          $pair = $round->getRaceOrderPair($i);
          $t1 = $teams[$pair[0] - 1];
          $t2 = $teams[$pair[1] - 1];

          foreach ($divisions as $div) {
            $race = new Race();
            $race->regatta = $this->REGATTA;
            $race->division = $div;
            $race->number = $racenum;
            $race->boat = $round->boat;
            $race->round = $round;
            $race->tr_team1 = $t1;
            $race->tr_team2 = $t2;
            $new_races[] = $race;

            if (isset($sails[$i])) {
              $templ = $sails[$i][$pair[0]][(string)$div];
              $sail = new Sail();
              $sail->sail = $templ->sail;
              $sail->color = $templ->color;
              $sail->race = $race;
              $sail->team = $t1;
              $new_sails[] = $sail;

              $templ = $sails[$i][$pair[1]][(string)$div];
              $sail = new Sail();
              $sail->sail = $templ->sail;
              $sail->color = $templ->color;
              $sail->race = $race;
              $sail->team = $t2;
              $new_sails[] = $sail;
            }
          }
        }

        // Insert all at once
        foreach ($new_races as $race)
          DB::set($race, false);
        DB::insertAll($new_sails);
        $message = "Created new round.";
      }
      $this->REGATTA->setData(); // new races
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA(array($message, " ", new XA($this->link('races'), "Add another round"), ".")));
      Session::d('round');
      Session::d('round_teams');
    }

    // ------------------------------------------------------------
    // Create from existing
    // ------------------------------------------------------------
    if (isset($args['create-from-existing'])) {
      $templ = $rounds[DB::$V->reqKey($args, 'template', $rounds, "Invalid template round provided.")];

      $round = new Round();
      $round->regatta = $this->REGATTA;
      $round->title = DB::$V->reqString($args, 'title', 1, 81, "Invalid round label. May not exceed 80 characters.");
      foreach ($rounds as $r) {
        if ($r->title == $round->title)
          throw new SoterException("Duplicate round title provided.");
      }
      $round->relative_order = count($rounds) + 1;

      $num_added = 0;
      $swap = DB::$V->incInt($args, 'swap', 1, 2, 0);
      $divs = $this->REGATTA->getDivisions();
      $racenum = count($this->REGATTA->getRaces(Division::A()));
      foreach ($this->REGATTA->getRacesInRound($templ, Division::A()) as $race) {
        if ($race->round != $templ) {
          $race->addRound($round);
          continue;
        }

        $racenum++;
        $num_added++;
        foreach ($divs as $div) {
          $tmprace = $this->REGATTA->getRace($div, $race->number);
          $newrace = new Race();
          $newrace->regatta = $this->REGATTA;
          $newrace->number = $racenum;
          $newrace->division = $div;
          $newrace->boat = $tmprace->boat;
          $newrace->round = $round;

          if ($swap > 0) {
            $newrace->tr_team1 = $tmprace->tr_team2;
            $newrace->tr_team2 = $tmprace->tr_team1;
          }
          else {
            $newrace->tr_team1 = $tmprace->tr_team1;
            $newrace->tr_team2 = $tmprace->tr_team2;
          }
          DB::set($newrace, false);
        }
      }

      // Also associate masters
      foreach ($templ->getMasters() as $master)
        $round->addMaster($master);

      $this->REGATTA->setData(); // new races
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA(array(sprintf("Added new round %s based on %s. ", $round, $templ),
                               new XA($this->link('round', array('r'=>$round->id)), "Order races"),
                               ".")));
    }

    // ------------------------------------------------------------
    // Add round: this may include race order and rotations
    // ------------------------------------------------------------
    if (isset($args['add-round'])) {
      $all_teams = array();
      foreach ($this->REGATTA->getTeams() as $team)
        $all_teams[$team->id] = $team;

      $num_teams = DB::$V->reqInt($args, 'num_teams', 2, count($all_teams) + 1, "Invalid number of teams provided.");

      $rounds = array();
      foreach ($this->REGATTA->getRounds() as $r)
        $rounds[$r->title] = $r;

      // title
      $round = new Round();
      $round->regatta = $this->REGATTA;
      $round->title = DB::$V->reqString($args, 'title', 1, 81, "Invalid round label. May not exceed 80 characters.");
      if (isset($rounds[$round->title]))
        throw new SoterException("Duplicate round title provided.");

      // TODO: insert round?
      $round->relative_order = count($rounds) + 1;

      $boat = DB::$V->reqID($args, 'boat', DB::$BOAT, "Invalid boat provided.");
      $ids = DB::$V->reqList($args, 'team', null, "No list of teams provided. Please try again.");
      $order = DB::$V->incList($args, 'order', count($ids));
      if (count($order) > 0)
        array_multisort($order, SORT_NUMERIC, $ids);

      $teams = array();
      foreach ($ids as $index => $id) {
        if ($order[$index] > 0) {
          if (!isset($all_teams[$id]))
            throw new SoterException("Invalid team ID provided: $id.");
          if (!isset($teams[$id]))
            $teams[$id] = $all_teams[$id];
        }
      }
      if (count($teams) != $num_teams)
        throw new SoterException(sprintf("Exactly %d must be provided; %d given.", $num_teams, count($teams)));
      $teams = array_values($teams);

      $divs = array(Division::A(), Division::B(), Division::C());
      $num_divs = count($divs);

      // Template?
      $template = DB::$V->incID($args, 'template', DB::$RACE_ORDER);
      $pairs = array();
      if ($template == null)
        $pairs = $this->makeRoundRobin($teams);
      else {
        if ($template->num_divisions != $num_divs)
          throw new SoterException("Invalid template chosen (wrong number of boats per team).");
        if ($template->num_teams != $num_teams)
          throw new SoterException("Invalid template chosen (wrong number of teams).");
        for ($i = 0; $i < ($num_teams * ($num_teams - 1)) / 2; $i++) {
          $pair = $template->getPair($i);
          $pairs[] = array($teams[$pair[0] - 1], $teams[$pair[1] - 1]);
        }
      }

      // Assign next race number
      $count = count($this->REGATTA->getRaces(Division::A()));

      // Create round robin
      $sailI = 0;
      $num_added = 0;
      $added = array();     // races to be added to this round
      $sails_added = array();
      foreach ($pairs as $pair) {
        $count++;
        foreach ($divs as $div) {
          $race = new Race();
          $race->division = $div;
          $race->number = $count;
          $race->boat = $boat;
          $race->regatta = $this->REGATTA;
          $race->round = $round;
          $race->tr_team1 = $pair[0];
          $race->tr_team2 = $pair[1];
          $added[] = $race;
        }
        $num_added++;
      }

      // Insert all at once
      DB::set($round);
      if (count($sails_added) > 0) {
        // This will automatically set the race as well
        $rot = $this->REGATTA->getRotation();
        foreach ($sails_added as $sail)
          $rot->setSail($sail);
      }
      else {
        DB::insertAll($added);
      }

      $this->REGATTA->setData(); // added new races
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA(sprintf("Added %d new races in round %s. ", $num_added, $round)));
      $this->redirect('races');
      return array();
    }

    // ------------------------------------------------------------
    // Slave round
    // ------------------------------------------------------------
    if (isset($args['add-slave-round'])) {
      $master_rounds = array();
      $rounds = array();
      foreach ($this->REGATTA->getRounds() as $r) {
        $rounds[$r->id] = $r;
        if (count($r->getMasters()) == 0)
          $master_rounds[$r->id] = $r;
      }

      // title
      $round = new Round();
      $round->regatta = $this->REGATTA;
      $round->title = DB::$V->reqString($args, 'title', 1, 81, "Invalid round label. May not exceed 80 characters.");
      foreach ($rounds as $r) {
        if ($r->title == $round->title)
          throw new SoterException("Duplicate round title provided.");
      }
      $round->relative_order = count($rounds) + 1;

      $boat = DB::$V->reqID($args, 'boat', DB::$BOAT, "Invalid boat provided.");

      // Expect the teams to be passed as an associative array indexed
      // by the existing-round's ID.
      $added_teams = array(); // global list of teams to insure no duplicates
      $teamlist = DB::$V->reqList($args, 'teams', null, "No list of teams provided.");
      $masters = array();
      foreach ($teamlist as $id => $list) {
        if (!isset($master_rounds[$id]))
          throw new SoterException("Invalid round provided: $id.");
        if (!is_array($list))
          throw new SoterException(sprintf("No teams provided for round \"%s\".", $master_rounds[$id]));
        $masters[$id] = array();

        // Create list of possible teams for this round
        $round_teams = array();
        foreach ($this->REGATTA->getTeamsInRound($master_rounds[$id]) as $team)
          $round_teams[$team->id] = $team;

        foreach ($list as $tid) {
          if (!isset($round_teams[$tid]))
            throw new SoterException(sprintf("Invalid team provided for round \"%s\": %s.", $master_rounds[$id], $tid));

          if (isset($added_teams[$tid]))
            throw new SoterException(sprintf("The same team (%s) may not advance from multiple rounds.", $round_teams[$tid]));
          $masters[$id][$tid] = $round_teams[$tid];
          $added_teams[$tid] = $round_teams[$tid];
        }

        // At least two teams must be imported from each round
        if (count($masters[$id]) < 2)
          throw new SoterException("At least two teams must advance from each round.");
      }
      if (count($masters) < 2)
        throw new SoterException("At least two (independent) rounds must be provided.");

      // ------------------------------------------------------------
      // Start creating the races!
      // ------------------------------------------------------------
      // Create list of existing pairs
      $prev_races = array(); // map of "<teamID>-<teamID>" => Race
      foreach ($masters as $id => $teamlist) {
        $r = $master_rounds[$id];
        foreach ($this->REGATTA->getRacesInRound($r, Division::A(), false) as $race) {
          $id = sprintf('%s-%s', $race->tr_team1->id, $race->tr_team2->id);
          $prev_races[$id] = $race;
        }
      }

      $count = count($this->REGATTA->getRaces(Division::A()));
      $divs = $this->REGATTA->getDivisions();
      $added = array();
      $duplicate = array();

      foreach ($this->makeRoundRobin($added_teams) as $pair) {
        $id1 = sprintf('%s-%s', $pair[0]->id, $pair[1]->id);
        $id2 = sprintf('%s-%s', $pair[1]->id, $pair[0]->id);

        $race = null;
        if (isset($prev_races[$id1]))
          $race = $prev_races[$id1];
        elseif (isset($prev_races[$id2]))
          $race = $prev_races[$id2];

        if ($race != null) {
          $duplicate[] = $race;
          for ($i = 1; $i < count($divs); $i++)
            $duplicate[] = $this->REGATTA->getRace($divs[$i], $race->number);
        }
        else {
          // new race
          $count++;
          foreach ($divs as $div) {
            $race = new Race();
            $race->division = $div;
            $race->number = $count;
            $race->boat = $boat;
            $race->regatta = $this->REGATTA;
            $race->round = $round;
            $race->tr_team1 = $pair[0];
            $race->tr_team2 = $pair[1];
            $added[] = $race;
          }
        }
      }

      DB::insertAll($added);

      foreach ($duplicate as $race)
        $race->addRound($round);

      // master-slave relationship
      foreach ($masters as $id => $list) {
        $round->addMaster($master_rounds[$id], count($list));
      }

      $this->REGATTA->setData(); // added new races
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA(array("Added new \"completion\" round. ",
                               new XA($this->link('round', array('r'=>$round->id)), "Order races"),
                               ".")));
      return array();
    }
    return array();
  }

  /**
   * Returns elements created in associative array
   *
   */
  private function processStep1(Array $args, Round $round, Array $rounds, Array $divisions) {
    $round->title = DB::$V->reqString($args, 'title', 1, 61, "Invalid or missing name.");
    foreach ($rounds as $r) {
      if ($r->title == $round->title)
        throw new SoterException("Duplicate round title provided.");
    }

    $clean_races = false;
    $clean_teams = false;
    $clean_rotation = false;
    $group_size = 2 * count($divisions);

    $num_teams = DB::$V->reqInt($args, 'num_teams', 2, count($this->REGATTA->getTeams()) + 1, "Invalid number of teams provided.");
    if ($num_teams != $round->num_teams) {
      $round->num_teams = $num_teams;
      $clean_races = true;
      $clean_teams = true;
    }

    $freq = DB::$V->reqKey($args, 'rotation_frequency', Race_Order::getFrequencyTypes(), "Invalid rotation frequency requested.");
    if ($freq != $round->rotation_frequency) {
      $round->rotation_frequency = $freq;
      $clean_races = true;
      $clean_rotation = true;
    }

    if ($round->rotation_frequency == Race_Order::FREQUENCY_NONE) {
      $round->num_boats = count($divisions) * $round->num_teams;
    }
    else {
      $num_boats = DB::$V->reqInt($args, 'num_boats', $group_size, 101, "Invalid number of boats provided.");
      if ($num_boats % $group_size != 0)
        throw new SoterException(sprintf("Number of boats must be divisible by %d.", $group_size));
      if ($num_boats != $round->num_boats) {
        $round->num_boats = $num_boats;
        $clean_races = true;
        $clean_rotation = true;
      }
    }

    if ($clean_teams)
      Session::d('round_teams');
    if ($clean_races)
      $round->race_order = null;
    if ($clean_rotation)
      $round->rotation = null;

    $round->boat = DB::$V->reqID($args, 'boat', DB::$BOAT, "Invalid or missing boat.");
    return array();
  }

  private function processStep2(Array $args, Round $round) {
    $num_races = ($round->num_teams * ($round->num_teams - 1)) / 2;
    $map = DB::$V->reqMap($args, array('team1', 'team2'), $num_races, "Invalid team order.");
    $swp = DB::$V->incList($args, 'swap');

    // All handshakes must be accounted for
    $handshakes = array();
    for ($i = 1; $i <= $round->num_teams; $i++) {
      for ($j = $i + 1; $j <= $round->num_teams; $j++) {
        $shake = sprintf("%d-%d", $i, $j);
        $handshakes[$shake] = $shake;
      }
    }

    $pairings = array();
    foreach ($map['team1'] as $i => $team1) {
      $team1 = DB::$V->reqInt($map['team1'], $i, 1, $round->num_teams + 1, "Invalid first team index provided.");
      $team2 = DB::$V->reqInt($map['team2'], $i, 1, $round->num_teams + 1, "Invalid partner provided.");
      if (in_array($i, $swp)) {
        $team3 = $team1;
        $team1 = $team2;
        $team2 = $team3;
      }
      if ($team1 == $team2)
        throw new SoterException("Teams cannot sail against themselves.");
      $shake = sprintf("%d-%d", $team1, $team2);
      $pair = $shake;
      if ($team2 < $team1)
        $shake = sprintf("%d-%d", $team2, $team1);
      if (!isset($handshakes[$shake]))
        throw new SoterException("Invalid team pairing provided.");
      unset($handshakes[$shake]);
      $pairings[] = $pair;
    }

    if (count($pairings) < $num_races)
      throw new SoterException("Not all pairings have been accounted for.");

    $round->race_order = $pairings;
    return array();
  }

  private function processStep3(Array $args, Round $round, Array $divisions) {
    $group_size = 2 * count($divisions);

    $s = array();
    $c = array();

    if ($round->rotation_frequency == Race_Order::FREQUENCY_FREQUENT ||
        $round->rotation_frequency == Race_Order::FREQUENCY_INFREQUENT) {
      $sails = DB::$V->reqList($args, 'sails', $round->num_boats, "Missing list of sails.");
      $c = DB::$V->incList($args, 'colors', $round->num_boats);

      // make sure all sails are present and distinct
      foreach ($sails as $sail) {
        $sail = trim($sail);
        if ($sail == "")
          throw new SoterException("Empty sail provided.");
        if (in_array($sail, $s))
          throw new SoterException("Duplicate sail \"$sail\" provided.");
        $s[] = $sail;
      }
    }
    else {
      $num_entries = $round->num_teams * count($divisions);

      // Assign all sails and colors to sails1 and colors1
      $sails = DB::$V->reqList($args, 'sails', $num_entries, "Invalid list of sails provided.");
      $c = DB::$V->incList($args, 'colors', $num_entries);

      foreach ($sails as $i => $sail) {
        $sail = trim($sail);
        if ($sail == "")
          throw new SoterException("Empty sail provided.");
        if (in_array($sail, $s))
          throw new SoterException("Duplicate sail \"$sail\" provided.");
        $s[] = $sail;
      }
    }
    $rot = new TeamRotation();
    $rot->sails = $s;
    $rot->colors = $c;

    $round->rotation = $rot;
    return array();
  }

  private function processStep4(Array $args, Round $round) {
    $all_teams = array();
    foreach ($this->REGATTA->getTeams() as $team)
      $all_teams[$team->id] = $team;

    $ids = DB::$V->incList($args, 'team');
    $order = DB::$V->incList($args, 'order', count($ids));
    if (count($order) > 0)
      array_multisort($order, SORT_NUMERIC, $ids);

    $teams = array();
    foreach ($ids as $index => $id) {
      if (!isset($order[$index]) || $order[$index] > 0) {
        if (!isset($all_teams[$id]))
          throw new SoterException("Invalid team ID provided: $id.");
        if (!isset($teams[$id]))
          $teams[$id] = $all_teams[$id];
      }
    }

    if (count($teams) > 0 && count($teams) != $round->num_teams)
      throw new SoterException("Invalid number of teams specified: either all teams or none.");

    return $teams;
  }

  private function calculateNextRaceNumber(Round $round) {
    $race_num = 0;
    foreach ($this->REGATTA->getRounds() as $r) {
      if ($r->id == $round->id)
        break;
      if ($r->race_order != null)
        $race_num += count($r->race_order);
      else
        $race_num += count($this->REGATTA->getRacesInRound($r, Division::A(), false));
    }
    return $race_num;
  }

  /**
   * Creates a round-robin from the given items
   *
   * @param Array $items the items to pair up in round robin
   * @param boolean $swap true to switch the normal order of the teams
   * @return Array:Array a list of all the pairings
   */
  private function makeRoundRobin($items, $swap = false) {
    if (count($items) < 2)
      throw new InvalidArgumentException("There must be at least two items.");
    if (count($items) == 2)
      return array($items);

    $list = array();
    $first = array_shift($items);
    foreach ($this->pairup($first, $items, $swap) as $other)
      $list[] = $other;
    foreach ($this->makeRoundRobin($items, $swap) as $pair)
      $list[] = $pair;
    return $list;
  }

  private function pairup($first, Array $rest, $swap = false) {
    foreach ($rest as $other)
      $list[] = ($swap) ? array($other, $first) : array($first, $other);
    return $list;
  }
}
?>