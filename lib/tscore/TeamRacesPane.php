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

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Edit Races", $user, $reg);
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
    // Specific round?
    // ------------------------------------------------------------
    $rounds = $this->REGATTA->getRounds();
    if (($round = DB::$V->incID($args, 'r', DB::$ROUND)) !== null) {
      foreach ($rounds as $r) {
        if ($r->id == $round->id) {
          $this->fillRound($round);
          return;
        }
      }
      Session::pa(new PA("Invalid round requested.", PA::E));
      $this->redirect();
    }

    // ------------------------------------------------------------
    // Current rounds (offer to reorder them)
    // ------------------------------------------------------------
    if (count($rounds) > 0) {
      $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tablesort.js'));
      $this->PAGE->addContent($p = new XPort("Current rounds"));
      $p->add($f = $this->createForm());
      $f->add(new XP(array(), "Click on the round below to edit the races in that round."));
      $f->add(new FItem("Round order:", $tab = new XQuickTable(array('id'=>'divtable', 'class'=>'narrow'), array("#", "Title", ""))));
      foreach ($rounds as $i => $round)
        $tab->addRow(array(new XTD(array(), array(new XTextInput('order[]', ($i + 1), array('size'=>2)),
                                                  new XHiddenInput('round[]', $round->id))),
                           new XTD(array('class'=>'drag'), $round),
                           new XA(WS::link(sprintf('/score/%s/races', $this->REGATTA->id), array('r'=>$round->id)), "Edit")),
                     array('class'=>'sortable'));
      $f->add(new XSubmitP('order-rounds', "Reorder"));
    }

    // ------------------------------------------------------------
    // Create from previous
    // ------------------------------------------------------------
    if (count($rounds) > 0) {
      $this->PAGE->addContent($p = new XPort("Create from existing round"));
      $p->add($f = $this->createForm());
      $f->add(new XP(array(), "Create a new round by using an existing round's races as references."));
      $f->add(new FItem("Round label:", new XTextInput('title', "Round " . (count($rounds) + 1))));
      $f->add(new FItem("Previous round:", XSelect::fromDBM('template', $rounds)));
      $f->add($fi = new FItem("Swap teams:", new XCheckboxInput('swap', 1, array('id'=>'chk-swap'))));
      $fi->add(new XLabel('chk-swap', "Reverse the teams in each race."));
      $f->add(new XSubmitP('create-from-existing', "Create round"));
    }

    // ------------------------------------------------------------
    // Add round
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Create new round"));
    $p->add($form = $this->createForm());
    $form->add(new XP(array(),
                   array("Choose the teams which will participate in the round to be added. ",
                         Conf::$NAME,
                         " will create the necessary races so that each team sails head-to-head against every other team (round-robin). Make sure to add an appropriate label for the round.")));
    $form->add(new XP(array(),
                      "To include a team, place a number next to the team name indicating their seeding order for the round-robin. Order numbers in ascending order. Teams with no number will not be included in the round-robin."));
    $form->add(new FItem("Round label:", new XTextInput('title', "Round " . (count($rounds) + 1))));

    $boats = DB::getBoats();
    $boatOptions = array();
    foreach ($boats as $boat)
      $boatOptions[$boat->id] = $boat->name;
    $form->add(new FItem("Boat:", XSelect::fromArray('boat', $boatOptions)));
    // $form->add($fi = new FItem("Meetings:", new XTextInput('meetings', 1)));
    // $fi->add(new XMessage("E.g., 1 for \"single\", 2 for \"double round-robin\""));

    $this->PAGE->head->add(new XScript('text/javascript', null, '
var TL = null;
var TL_INPUTS = Array();
function addTeamToRound(id) {
  if (!TL) {
    TL = document.getElementById("teams-list");
    if (!TL)
      return;
    var inputs = TL.getElementsByTagName("input");
    for (var i = 0; i &lt; inputs.length; i++) {
      if (inputs[i].type == "text")
        TL_INPUTS.push(inputs[i]);
    }
  }
  var elem = document.getElementById(id);
  if (!elem || elem.value != "")
    return;
  var max = 0;
  for (var i = 0; i &lt; TL_INPUTS.length; i++) {
    var num = Number(TL_INPUTS[i].value);
    if (num > max)
      max = num;
  }
  elem.value = (max + 1);
}
'));

    $form->add($ul = new XUl(array('id'=>'teams-list')));
    foreach ($this->REGATTA->getTeams() as $team) {
      $id = 'team-'.$team->id;
      $ul->add(new XLi(array(new XHiddenInput('team[]', $team->id),
                             new XTextInput('order[]', "", array('id'=>$id)),
                             new XLabel($id, $team,
                                        array('onclick'=>sprintf('addTeamToRound("%s");', $id))))));
    }

    $form->add(new XSubmitP('add-round', "Add round"));
  }

  private function fillRound($round) {
    $this->PAGE->addContent(new XP(array(), new XA(WS::link(sprintf('/score/%s/races', $this->REGATTA->id)), "← Back to races")));

    $teamOpts = array();
    $teamFullOpts = array("null" => "");
    foreach ($this->REGATTA->getTeams() as $team) {
      $teamOpts[$team->id] = $team;
      $teamFullOpts[$team->id] = $team;
    }

    $boats = DB::getBoats();
    $boatOptions = array();
    foreach ($boats as $boat)
      $boatOptions[$boat->id] = $boat->name;

    // ------------------------------------------------------------
    // Edit round name (and other attributes)
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Edit round information"));
    $p->add($form = $this->createForm());
    $form->add(new FItem("Label:", new XTextInput('title', $round->title)));
    $form->add($p = new XSubmitP('edit-round', "Edit"));
    $p->add(new XHiddenInput('round', $round->id));

    // ------------------------------------------------------------
    // Current races
    // ------------------------------------------------------------
    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tablesort.js'));
    $has_finishes = false;
    $cur_races = $this->REGATTA->getRacesInRound($round, Division::A());
    if (count($cur_races) > 0) {
      $this->PAGE->addContent($p = new XPort("Edit races in round $round"));
      $p->add($form = $this->createForm());
      $form->set('id', 'edit-races-form');
      $form->add(new XNoScript("To reorder the races, indicate the relative desired order in the first cell."));
      $form->add(new XScript('text/javascript', null, 'var f = document.getElementById("edit-races-form"); var p = document.createElement("p"); p.appendChild(document.createTextNode("To reorder the races, move the rows below by clicking and dragging on the first cell (\"#\") of that row.")); f.appendChild(p);'));
      $form->add(new XP(array(), "You may also edit the associated boat for each race. Click the \"Edit races\" button to save changes. Extra (unused) races will be removed at the end of the regatta."));
      if (count($cur_races) > 20)
        $form->add(new XP(array('class'=>'warning'), "Hint: For large rotations, click \"Edit races\" at the bottom of page often to save your work."));
      $form->add(new XNoScript(array(new XP(array(),
                                            array(new XStrong("Important:"), " check the edit column if you wish to edit that race. The race will not be updated regardless of changes made otherwise.")))));
      $form->add($tab = new XQuickTable(array('id'=>'divtable', 'class'=>'teamtable'), array("Order", "#", "First team", "← Swap →", "Second team", "Boat")));
      foreach ($cur_races as $i => $race) {
        $teams = $this->REGATTA->getRaceTeams($race);
        if (count($this->REGATTA->getFinishes($race)) > 0)
          $has_finishes = true;
        $tab->addRow(array(new XTD(array(),
                                   array(new XTextInput('order[]', ($i + 1), array('size'=>2)),
                                         new XHiddenInput('race[]', $race->number))),
                           new XTD(array('class'=>'drag'), $race->number),
                           $teams[0],
			   new XCheckboxInput('swap[]', $race->id),
                           $teams[1],
                           XSelect::fromArray('boat[]', $boatOptions, $race->boat->id)),
                     array('class'=>'sortable'));
      }
      $form->add($p = new XSubmitP('edit-races', "Edit races"));
      $p->add(new XHiddenInput('round', $round->id));
    }

    // ------------------------------------------------------------
    // With no scored races, offer to delete
    // ------------------------------------------------------------
    if (!$has_finishes) {
      $this->PAGE->addContent($p = new XPort("Delete round"));
      $p->add(new XP(array(), "Since there are no scored races for this round, you may delete the entire round from the regatta, but note that this is not recoverable."));
      $p->add($form = $this->createForm());
      $form->add($p = new XSubmitP('delete-round', "Delete"));
      $p->add(new XHiddenInput('round', $round->id));
    }
  }

  /**
   * Processes new races and edits to existing races
   */
  public function process(Array $args) {
    // ------------------------------------------------------------
    // Order rounds
    // ------------------------------------------------------------
    if (isset($args['order-rounds'])) {
      $rounds = array();
      foreach ($this->REGATTA->getRounds() as $round)
        $rounds[$round->id] = $round;
      if (count($rounds) == 0)
        throw new SoterException("No rounds exist to reorder.");

      $rids = DB::$V->reqList($args, 'round', count($rounds), "Invalid list of rounds to reorder.");
      $order = DB::$V->incList($args, 'order', count($rids));
      if (count($order) > 0)
        array_multisort($order, SORT_NUMERIC, $rids);

      // validate that all rounds are accounted for, as races are
      // renumbered
      $divs = $this->REGATTA->getDivisions();

      $edited = array();
      $races = array();
      $roundnum = 1;
      $racenum = 1;
      foreach ($rids as $rid) {
        if (!isset($rounds[$rid]))
          throw new SoterException("Invalid round requested.");
        $round = $rounds[$rid];
        $round->relative_order = $roundnum++;
        foreach ($this->REGATTA->getRacesInRound($round, Division::A()) as $race) {
          foreach ($divs as $div) {
            $r = $this->REGATTA->getRace($div, $race->number);
            $r->number = $racenum;
            $races[] = $r;
          }
          $racenum++;
        }
        unset($rounds[$rid]);
        $edited[] = $round;
      }

      // commit rounds, and races
      foreach ($edited as $round)
        DB::set($round, true);
      foreach ($races as $r)
        DB::set($r, true);

      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA("Edited the round order."));
    }

    // ------------------------------------------------------------
    // Edit round data
    // ------------------------------------------------------------
    if (isset($args['edit-round'])) {
      $round = DB::$V->reqID($args, 'round', DB::$ROUND, "Invalid round to edit.");
      $title = DB::$V->reqString($args, 'title', 1, 81, "Invalid new label for round.");
      if ($title == $round->title)
        Session::pa(new PA("No change in title.", PA::I));
      else {
        $round->title = $title;
        DB::set($round);
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
        Session::pa(new PA("Edited round data for $round."));
      }
    }

    // ------------------------------------------------------------
    // Delete round
    // ------------------------------------------------------------
    if (isset($args['delete-round'])) {
      $rounds = array();
      foreach ($this->REGATTA->getRounds() as $r)
        $rounds[$r->id] = $r;

      $round = $rounds[DB::$V->reqKey($args, 'round', $rounds, "Invalid round to delete.")];
      // Check that there are no finishes
      foreach ($this->REGATTA->getRacesInRound($round) as $race) {
        if (count($this->REGATTA->getFinishes($race)) > 0)
          throw new SoterException("Cannot remove the round because race $race has scored.");
      }
      DB::remove($round);

      // Order races of all rounds AFTER this one
      $divs = $this->REGATTA->getDivisions();

      $round_num = 1;
      $race_num = 1;
      foreach ($rounds as $rid => $other) {
        if ($other->relative_order < $round->relative_order) {
          $race_num += count($this->REGATTA->getRacesInRound($other, Division::A()));
          $round_num++;
          continue;
        }
        $other->relative_order = $round_num++;
        DB::set($other, true);
        foreach ($this->REGATTA->getRacesInRound($other, Division::A()) as $race) {
          foreach ($divs as $div) {
            $r = $this->REGATTA->getRace($div, $race->number);
            $r->number = $race_num;
            DB::set($r, true);
          }
          $race_num++;
        }
      }
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA("Removed round $round."));
    }

    // ------------------------------------------------------------
    // Create from existing
    // ------------------------------------------------------------
    if (isset($args['create-from-existing'])) {
      $rounds = array();
      foreach ($this->REGATTA->getRounds() as $r)
	$rounds[$r->id] = $r;

      $templ = $rounds[DB::$V->reqKey($args, 'template', $rounds, "Invalid template round provided.")];

      $round = new Round();
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
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA(sprintf("Added new round %s with %d race(s).", $round, $num_added)));
      $this->redirect('races', array('r'=>$round->id));
    }

    // ------------------------------------------------------------
    // Add round
    // ------------------------------------------------------------
    if (isset($args['add-round'])) {
      $rounds = $this->REGATTA->getRounds();

      // title
      $round = new Round();
      $round->title = DB::$V->reqString($args, 'title', 1, 81, "Invalid round label. May not exceed 80 characters.");
      foreach ($rounds as $r) {
        if ($r->title == $round->title)
          throw new SoterException("Duplicate round title provided.");
      }
      $round->relative_order = count($rounds) + 1;

      $boat = DB::$V->reqID($args, 'boat', DB::$BOAT, "Invalid boat provided.");
      $map = DB::$V->reqMap($args, array('order', 'team'), null, "No list of ordered teams provided. Please try again.");

      $ord = $map['order'];
      $ids = $map['team'];
      array_multisort($ord, SORT_NUMERIC, $ids, SORT_STRING);

      // $meetings = DB::$V->reqInt($args, 'meetings', 1, 11, "Invalid meeting count. Must be between 1 and 10.");
      $meetings = 1;

      $teams = array();
      foreach ($ids as $index => $id) {
        if (trim($ord[$index]) != "" && ($team = $this->REGATTA->getTeam($id)) !== null)
          $teams[] = $team;
      }
      if (count($teams) < 1)
        throw new SoterException("Not enough teams provided: there must be at least two. Please try again.");

      // Assign next race number
      $count = count($this->REGATTA->getRaces(Division::A()));

      // Create round robin
      $num_added = 0;
      $divs = array(Division::A(), Division::B(), Division::C());

      $swap = false;
      for ($meeting = 0; $meeting < $meetings; $meeting++) {
        foreach ($this->pairup($teams, $swap) as $pair) {
          $count++;
          foreach ($divs as $div) {
            $race = new Race();
            $race->division = $div;
            $race->number = $count;
            $race->boat = $boat;
            $race->round = $round;
            $this->REGATTA->setRace($race);
          }
          $this->REGATTA->setRaceTeams($race, $pair[0], $pair[1]);
          $num_added++;
        }
        $swap = !$swap;
      }
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA("Added $num_added new races in round $round."));
      $this->redirect('races', array('r'=>$round->id));
      return array();
    }

    // ------------------------------------------------------------
    // Edit races
    // ------------------------------------------------------------
    if (isset($args['edit-races'])) {
      $rounds = array();
      foreach ($this->REGATTA->getRounds() as $r)
        $rounds[$r->id] = $r;
      $round = $rounds[DB::$V->reqKey($args, 'round', $rounds, "Invalid round provided.")];

      $map = DB::$V->reqMap($args, array('race', 'boat'), null, "Invalid list of race and boats.");
      // If order list provided (and valid), then use it
      $ord = DB::$V->incList($args, 'order', count($map['race']));
      if (count($ord) > 0)
        array_multisort($ord, SORT_NUMERIC, $map['race'], $map['boat']);

      // Make sure that every race number is accounted for
      $numbers = array();
      foreach ($this->REGATTA->getRacesInRound($round) as $r) {
        if (!isset($numbers[$r->number]))
          $numbers[$r->number] = array();
        $numbers[$r->number][] = $r;
      }
      $pool = array_keys($numbers);
      sort($pool);

      $swaplist = DB::$V->incList($args, 'swap');
      $to_save = array();
      foreach ($map['race'] as $i => $number) {
        if (!isset($numbers[$number]))
          throw new SoterException("Invalid race number $number for round $round.");

        $boat = DB::getBoat($map['boat'][$i]);
        if ($boat === null)
          throw new SoterException("Invalid boat specified.");

        foreach ($numbers[$number] as $race) {
          $edited = false;
          if ($race->boat != $boat) {
            $race->boat = $boat;
            $edited = true;
          }
          if ($race->number != $pool[$i]) {
            $race->number = $pool[$i];
            $edited = true;
          }
	  if (in_array($race->id, $swaplist)) {
	    $team1 = $race->tr_team1;
	    $race->tr_team1 = $race->tr_team2;
	    $race->tr_team2 = $team1;
	    $edited = true;
	  }
          if ($edited)
            $to_save[] = $race;
        }
        unset($numbers[$number]);
      }
      if (count($numbers) > 0)
        throw new SoterException("Not all races in round are accounted for.");

      foreach ($to_save as $race)
        DB::set($race, true);
        
      if (count($to_save) == 0)
        Session::pa(new PA("No races were updated.", PA::I));
      else {
        Session::pa(new PA(sprintf("Races updated for round %s.", $round)));
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      }
    }
    return array();
  }

  /**
   * Creates a round-robin from the given items
   *
   * @param Array $items the items to pair up in round robin
   * @param boolean $swap true to switch the normal order of the teams
   * @return Array:Array a list of all the pairings
   */
  private function pairup($items, $swap = false) {
    if (count($items) < 2)
      throw new InvalidArgumentException("There must be at least two items.");
    if (count($items) == 2)
      return array($items);

    $list = array();
    $first = array_shift($items);
    foreach ($items as $other)
      $list[] = ($swap) ? array($other, $first) : array($first, $other);
    foreach ($this->pairup($items, $swap) as $pair)
      $list[] = $pair;
    return $list;
  }
}
?>