<?php
/**
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

/**
 * (Re)Enters finishes
 *
 * 2010-02-25: Allow entering combined divisions. Of course, deal with
 * the team name entry as well as rotation
 *
 * @author Dayan Paez
 * @created 2010-01-24
 */
class EnterFinishPane extends AbstractPane {

  private $ACTIONS = array("ROT" => "Sail numbers from rotation",
			   "TMS" => "Team names");

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Enter finishes", $user, $reg);
    $this->title = "Enter/edit";
    $this->urls[] = "finish";
    $this->urls[] = "finishes";
    $this->urls[] = "enter-finish";
  }


  /**
   * Fills the page in the case of a combined division scoring
   *
   * @param Array $args the argument
   */
  private function fillCombined(Array $args) {
    $divisions = $this->REGATTA->getDivisions();

    // Determine race to display: either as requested or the next
    // unscored race, or the last race.
    $race = null;
    if (isset($args['chosen_race'])) $race = $args['chosen_race'];
    if ($race == null) {
      $races = $this->REGATTA->getUnscoredRaces();
      $race = array_shift($races);
    }
    if ($race == null) {
      $races = $this->REGATTA->getScoredRaces();
      $race = array_pop($races);
    }

    $rotation = $this->REGATTA->getRotation();

    $this->PAGE->addHead(new GenericElement("script",
					    array(new Text()),
					    array("type"=>"text/javascript",
						  "src"=>"/inc/js/finish.js")));

    $this->PAGE->addContent($p = new Port("Choose race number"));

    // ------------------------------------------------------------
    // Choose race
    // ------------------------------------------------------------
    $p->addChild($form = $this->createForm());
    $form->addAttr("id", "race_form");
    $form->addChild(new Para("This regatta is being scored with combined divisions. " .
			     "Please enter any race in any division to enter finishes " .
			     "for that race number across all divisions."));

    $form->addChild($fitem = new FItem("Race:", 
				       new FText("chosen_race",
						 $race,
						 array("size"=>"4",
						       "maxlength"=>"3",
						       "id"=>"chosen_race",
						       "class"=>"narrow"))));

    // Table of possible races
    $race_nums = array();
    foreach ($this->REGATTA->getUnscoredRaces($divisions[0]) as $r)
      $race_nums[] = $r->number;
    $fitem->addChild($tab = new Table());
    $tab->addAttr("class", "narrow");
    $tab->addHeader(new Row(array(Cell::th("#"))));
    $cont = Utilities::makeRange($race_nums);
    if (empty($cont)) $cont = "--";
    $tab->addRow(new Row(array(new Cell($cont))));

    // Using?
    $using = (isset($args['finish_using'])) ?
      $args['finish_using'] : "ROT";

    if (count($rotation->getRaces()) == 0) {
      unset($this->ACTIONS["ROT"]);
      $using = "TMS";
    }
    
    $form->addChild(new FItem("Using:",
			      $fsel = new FSelect("finish_using",
						  array($using))));
    $fsel->addOptions($this->ACTIONS);

    $form->addChild(new FSubmit("choose_race",
				"Change race"));

    // ------------------------------------------------------------
    // Enter finishes
    // ------------------------------------------------------------
    $races = array();
    $finishes = array();
    foreach ($divisions as $div) {
      $r = $this->REGATTA->getRace($div, $race->number);
      $races[] = $r;
      $f2 = $this->REGATTA->getFinishes($r);
      
      $finishes = array_merge($finishes, $this->REGATTA->getFinishes($r));
    }
    usort($finishes, "Finish::compareEntered");

    $title = sprintf("Add/edit finish for race %s across all divisions", $race->number);
    $this->PAGE->addContent($p = new Port($title));
    $p->addChild($form = $this->createForm());
    $form->addAttr("id", "finish_form");

    $form->addChild(new FHidden("race", $race->id));
    if ($using == "ROT") {
      // ------------------------------------------------------------
      // Rotation-based
      // ------------------------------------------------------------
      $form->addChild($fitem = new FItem("Enter sail numbers:",
					 $tab = new Table()));
      $tab->addAttr("class", "narrow");
      $tab->addAttr("class", "coordinate");
      $tab->addHeader(new Row(array(new Cell("Possible sails",
					     array("colspan"=>"3"), 1))));

      // - Fill possible sails
      $pos_sails = array();
      foreach ($races as $race)
	$pos_sails = array_merge($pos_sails, $rotation->getSails($race));
      sort($pos_sails);
      $pos_sails = array_unique($pos_sails);
      $row = array();
      foreach ($pos_sails as $aPS) {
	$row[] = new Cell($aPS, array("name"=>"pos_sail",
				      "class"=>"pos_sail",
				      "id"=>"pos_sail"));
	if (count($row) == 3) {
	  $tab->addRow(new Row($row));
	  $row = array();
	}
      }
      if (!empty($row))
	$tab->addRow(new Row($row));

      // - List of race finishes
      $fitem->addChild(new Div(array($enum = new Enumerate()),
			       array("class"=>array("form_b", "fuse_columns"))));
      $enum->addAttr("id", "finish_list");
      for ($i = 0; $i < count($pos_sails); $i++) {
	if (count($finishes) > 0)
	  $current_sail = $rotation->getSail($finishes[$i]->race, $finishes[$i]->team);
	else
	  $current_sail = "";

	$enum->addChild($item = new LItem());
	$item->addChild(new FText("p" . $i, $current_sail,
				  array("id"=>"sail" . $i,
					"tabindex"=>($i+1),
					"onkeyup"=>"checkSails()",
					"size"=>"2")));
	$item->addChild(new Image("/img/question.png",
				  array("alt"=>"Waiting for input",
					"id"=>"check" . $i)));
      }

      // Submit buttom
      //$form->addChild(new FReset("reset_finish", "Reset"));
      $form->addChild(new FSubmit("f_places",
				  sprintf("Enter finish for race %s", $race->number),
				  array("id"=>"submitfinish", "tabindex"=>($i+1))));
    }
    else {
      // ------------------------------------------------------------
      // Team lists
      // ------------------------------------------------------------
      $form->addChild($fitem = new FItem("Enter teams:",
					 $tab = new Table()));
      $tab->addAttr("class", "narrow");
      $tab->addAttr("class", "ordinate");
      $tab->addHeader(new Row(array(Cell::th("Teams"))));

      // - Fill teams
      $teams = $this->REGATTA->getTeams();
      $attrs = array("name" =>"pos_team",
		     "id"   =>"pos_team",
		     "class"=>"pos_sail");
      
      foreach ($divisions as $div) {
	foreach ($teams as $team) {
	  $name = sprintf("%s: %s %s",
			  $div,
			  $team->school->nick_name,
			  $team->name);
	  $attrs["value"] = sprintf("%s,%s", $div, $team->id);
	  $tab->addRow(new Row(array(new Cell($name, $attrs))));
	}
      }

      // - List of finishes
      $fitem->addChild(new Div(array($enum = new Enumerate()),
			       array("class"=>array("form_b", "fuse_columns"))));
      $enum->addAttr("id", "finish_list");
      $team_opts = array("" => "");
      foreach ($divisions as $div) {
	foreach ($teams as $team) {
	  $team_opts[sprintf("%s,%s", $div, $team->id)] = sprintf("%s: %s %s",
								  $div,
								  $team->school->nick_name,
								  $team->name);
	}
      }
      for ($i = 0; $i < (count($teams) * count($divisions)); $i++) {
	if (count($finishes) > 0) {
	  $current_team = sprintf("%s,%s",
				  $finishes[$i]->race->division,
				  $finishes[$i]->team->id);
	}
	else
	  $current_team = "";
	$enum->addChild($item = new LItem());
	$item->addChild($sel = new FSelect("p" . $i, array($current_team),
					   array("id"=>"team" . $i,
						 "tabindex"=>($i+1),
						 "onchange"=>"checkTeams()")));
	$sel->addOptions($team_opts);
	$item->addChild(new Image("/img/question.png",
				  array("alt"=>"Waiting for input",
					"id"=>"check" . $i)));
      }

      // Submit buttom
      $form->addChild(new FSubmit("f_teams",
				  sprintf("Enter finish for race %s", $race->number),
				  array("id"=>"submitfinish", "tabindex"=>($i+1))));
    }
  }


  protected function fillHTML(Array $args) {

    if ($this->REGATTA->get(Regatta::SCORING) == Regatta::SCORING_COMBINED) {
      $this->fillCombined($args);
      return;
    }

    $divisions = $this->REGATTA->getDivisions();

    // Determine race to display: either as requested or the next
    // unscored race, or the last race
    $race = null;
    if (isset($args['chosen_race'])) $race = $args['chosen_race'];
    if ($race == null) {
      $races = $this->REGATTA->getUnscoredRaces();
      $race = array_shift($races);
    }
    if ($race == null) {
      $this->announce(new Announcement("No new races to score.", Announcement::WARNING));
      $this->redirect();
    }

    $rotation = $this->REGATTA->getRotation();

    $this->PAGE->addHead(new GenericElement("script",
					    array(new Text()),
					    array("type"=>"text/javascript",
						  "src"=>"/inc/js/finish.js")));

    $this->PAGE->addContent($p = new Port("Choose race"));

    // ------------------------------------------------------------
    // Choose race
    // ------------------------------------------------------------
    $p->addChild($form = $this->createForm());
    $form->addAttr("id", "race_form");

    $form->addChild($fitem = new FItem("Race:", 
				       new FText("chosen_race",
						 $race,
						 array("size"=>"4",
						       "maxlength"=>"3",
						       "id"=>"chosen_race",
						       "class"=>"narrow"))));

    // Table of possible races
    $fitem->addChild($tab = new Table());
    $tab->addAttr("class", "narrow");
    $tab->addHeader($hrow = new Row(array(), array("id"=>"pos_divs")));
    $tab->addRow($brow = new Row(array(), array("id"=>"pos_races")));
    foreach ($divisions as $div) {
      $hrow->addCell(Cell::th($div));
      $race_nums = array();
      foreach ($this->REGATTA->getUnscoredRaces($div) as $r)
	$race_nums[] = $r->number;
      $brow->addCell(new Cell(Utilities::makeRange($race_nums)));
    }

    // Using?
    $using = (isset($args['finish_using'])) ?
      $args['finish_using'] : "ROT";
    $form->addChild(new FItem("Using:",
			      $fsel = new FSelect("finish_using",
						  array($using))));
    $fsel->addOptions($this->ACTIONS);

    $form->addChild(new FSubmit("choose_race",
				"Change race"));

    // ------------------------------------------------------------
    // Enter finishes
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new Port("Add/edit finish for " . $race));
    $p->addChild($form = $this->createForm());
    $form->addAttr("id", "finish_form");

    $form->addChild(new FHidden("race", $race->id));
    if ($using == "ROT") {
      // ------------------------------------------------------------
      // Rotation-based
      // ------------------------------------------------------------
      $form->addChild($fitem = new FItem("Enter sail numbers:",
					 $tab = new Table()));
      $tab->addAttr("class", "narrow");
      $tab->addAttr("class", "coordinate");
      $tab->addHeader(new Row(array(new Cell("Possible sails",
					     array("colspan"=>"3"), 1))));

      // - Fill possible sails
      $pos_sails = $rotation->getSails($race);
      $row = array();
      foreach ($pos_sails as $aPS) {
	$row[] = new Cell($aPS, array("name"=>"pos_sail",
				      "class"=>"pos_sail",
				      "id"=>"pos_sail"));
	if (count($row) == 3) {
	  $tab->addRow(new Row($row));
	  $row = array();
	}
      }
      if (!empty($row))
	$tab->addRow(new Row($row));

      // - List of race finishes
      $finishes = $this->REGATTA->getFinishes($race);
      $fitem->addChild(new Div(array($enum = new Enumerate()),
			       array("class"=>array("form_b", "fuse_columns"))));
      $enum->addAttr("id", "finish_list");
      for ($i = 0; $i < count($pos_sails); $i++) {
	if (count($finishes) > 0) {
	  $current_sail = $rotation->getSail($race, $finishes[$i]->team);
	}
	else
	  $current_sail = "";
	$enum->addChild($item = new LItem());
	$item->addChild(new FText("p" . $i, $current_sail,
				  array("id"=>"sail" . $i,
					"tabindex"=>($i+1),
					"onkeyup"=>"checkSails()",
					"size"=>"2")));
	$item->addChild(new Image("/img/question.png",
				  array("alt"=>"Waiting for input",
					"id"=>"check" . $i)));
      }

      // Submit buttom
      // $form->addChild(new FReset("reset_finish", "Reset"));
      $form->addChild(new FSubmit("f_places",
				  sprintf("Enter finish for %s", $race),
				  array("id"=>"submitfinish", "tabindex"=>($i+1))));
    }
    else {
      // ------------------------------------------------------------
      // Team lists
      // ------------------------------------------------------------
      $form->addChild($fitem = new FItem("Enter teams:",
					 $tab = new Table()));
      $tab->addAttr("class", "narrow");
      $tab->addAttr("class", "ordinate");
      $tab->addHeader(new Row(array(Cell::th("Teams"))));

      // - Fill teams
      $teams = $this->REGATTA->getTeams();
      $attrs = array("name"=>"pos_team",
		     "class"=>"pos_sail",
		     "id"=>"pos_team");
      foreach ($teams as $team) {
	$name = sprintf("%s %s",
			$team->school->nick_name,
			$team->name);
	$attrs["value"] = $team->id;
	$tab->addRow(new Row(array(new Cell($name, $attrs))));
      }

      // - List of finishes
      $finishes = $this->REGATTA->getFinishes($race);
      $fitem->addChild(new Div(array($enum = new Enumerate()),
			       array("class"=>array("form_b", "fuse_columns"))));
      $enum->addAttr("id", "finish_list");
      $team_opts = array("" => "");
      foreach ($teams as $team) {
	$team_opts[$team->id] = sprintf("%s %s",
					$team->school->nick_name,
					$team->name);
      }
      for ($i = 0; $i < count($teams); $i++) {
	if (count($finishes) > 0)
	  $current_team = $finishes[$i]->team->id;
	else
	  $current_team = "";
	$enum->addChild($item = new LItem());
	$item->addChild($sel = new FSelect("p" . $i, array($current_team),
					   array("id"=>"team" . $i,
						 "tabindex"=>($i+1),
						 "onchange"=>"checkTeams()")));
	$sel->addOptions($team_opts);
	$item->addChild(new Image("/img/question.png",
				  array("alt"=>"Waiting for input",
					"id"=>"check" . $i)));
      }

      // Submit buttom
      $form->addChild(new FSubmit("f_teams",
				  sprintf("Enter finish for %s", $race),
				  array("id"=>"submitfinish", "tabindex"=>($i+1))));
    }
  }

  /**
   * Helper method processes combined division finishes
   *
   * @param Array $args as usual, the arguments
   */
  private function processCombined(Array $args) {
    $divisions = $this->REGATTA->getDivisions();

    // ------------------------------------------------------------
    // Choose race, can be a number or a full race
    // ------------------------------------------------------------
    if (isset($args['chosen_race'])) {
      try {
	if (is_numeric($args['chosen_race'])) {
	  $args['chosen_race'] = $this->REGATTA->getRace($divisions[0], (int)$args['chosen_race']);
	}
	else {
	  $race = Race::parse($args['chosen_race']);
	  $therace = $this->REGATTA->getRace($race->division, $race->number);
	  $args['chosen_race'] = $therace;
	}
      }
      catch (InvalidArgumentException $e) {
	$mes = sprintf("Invalid race (%s).", $args['chosen_race']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	unset($args['chosen_race']);
      }

      if (!isset($args['finish_using']) ||
	  !in_array($args['finish_using'], array_keys($this->ACTIONS))) {
	$args['finish_using'] = "ROT";
      }

      return $args;
    }

    // ------------------------------------------------------------
    // Enter finish by rotation
    // ------------------------------------------------------------
    $rotation = $this->REGATTA->getRotation();
    if (isset($args['f_places'])) {
      $race = Preferences::getObjectWithProperty($rotation->getRaces(),
						 "id",
						 $args['race']);
      if ($race == null) {
	$mes = sprintf("Invalid race (%s).", $args['race']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	unset($args['race']);
	return $args;
      }

      // Get all races and sails:
      // Ascertain that there are as many finishes as there are sails
      // participating in this regatta (every team has a finish). Make
      // associative array of sail numbers => teams
      $teams = $this->REGATTA->getTeams();
      $races = array();                     // alist: sail => race
      $sails = array();                     // alist: sail => team
      foreach ($divisions as $div) {
	$r = $this->REGATTA->getRace($div, $race->number);
	foreach ($teams as $t) {
	  $s = $rotation->getSail($r, $t);
	  $sails[$s] = $t;
	  $races[$s] = $r;
	}
      }

      $count = count($sails);
      $finishes = array();
      $now_fmt = "now + %d seconds";
      for ($i = 0; $i < $count; $i++) {

	// Verify
	if (!isset($args["p$i"])) {
	  $this->announce(new Announcement("Missing team(s).", Announcement::ERROR));
	  return $args;
	}
	$sail = $args["p$i"];
	// Possible sail
	if (!in_array($sail, array_keys($sails))) {
	  $mes = sprintf('Sail not in this race (%s).', $sail);
	  $this->announce(new Announcement($mes, Announcement::ERROR));
	  return $args;
	}

	$finish = new Finish(null, $races[$sail], $sails[$sail]);
	$finish->entered = new DateTime(sprintf($now_fmt, 3 * $i));
	$finishes[] = $finish;
	unset($sails[$sail]);
      }

      $this->REGATTA->setFinishes($finishes);

      // Reset
      unset($args['chosen_race']);
      $mes = sprintf("Finishes entered for race %s.", $race->number);
      $this->announce(new Announcement($mes));
    }

    // ------------------------------------------------------------
    // Enter finish by team
    // ------------------------------------------------------------
    if (isset($args['f_teams'])) {
      $race = Preferences::getObjectWithProperty($rotation->getRaces(),
						 "id",
						 $args['race']);
      if ($race == null) {
	$mes = sprintf("Invalid race (%s).", $args['race']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	unset($args['race']);
	return $args;
      }

      // Ascertain that each team has a stake in this finish. Make
      // alist of sail numbers => team, race for declaring finish
      // objects later on
      $teams = $this->REGATTA->getTeams();
      $races = array();
      $sails = array();
      foreach ($divisions as $div) {
	foreach ($this->REGATTA->getTeams() as $team) {
	  $index = sprintf("%s,%s", $div, $team->id);
	  
	  $races[$index] = $this->REGATTA->getRace($div, $race->number);
	  $sails[$index] = $team;
	}
      }
      
      $count = count($teams) * count($divisions);
      $finishes = array();
      $now_fmt = "now + %d seconds";
      for ($i = 0; $i < $count; $i++) {

	// Verify
	if (!isset($args["p$i"])) {
	  $this->announce(new Announcement("Missing team(s).", Announcement::ERROR));
	  return $args;
	}
	$team_id = $args["p$i"];

	// Possible team
	if (!in_array($team_id, array_keys($sails))) {
	  $mes = sprintf('Invalid team ID (%s).', $team_id);
	  $this->announce(new Announcement($mes, Announcement::ERROR));
	  return $args;
	}

	$finish = new Finish(null, $races[$team_id], $sails[$team_id]);
	$finish->entered = new DateTime(sprintf($now_fmt, 3 * $i));
	$finishes[] = $finish;
	unset($sails[$team_id]);
      }

      $this->REGATTA->setFinishes($finishes);

      // Reset
      unset($args['chosen_race']);
      $mes = sprintf("Finishes entered for race %s.", $race->number);
      $this->announce(new Announcement($mes));

      $args['finish_using'] = "TMS";
    }
    
    return $args;
  }


  public function process(Array $args) {

    if ($this->REGATTA->get(Regatta::SCORING) == Regatta::SCORING_COMBINED) {
      return $this->processCombined($args);
    }

    $divisions = $this->REGATTA->getDivisions();
    
    // ------------------------------------------------------------
    // Choose race
    // ------------------------------------------------------------
    if (isset($args['chosen_race'])) {
      try {
	$race = Race::parse($args['chosen_race']);
	$therace = $this->REGATTA->getRace($race->division, $race->number);
	$args['chosen_race'] = $therace;
      }
      catch (InvalidArgumentException $e) {
	$mes = sprintf("Invalid race (%s).", $args['chosen_race']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	unset($args['chosen_race']);
      }

      if (!isset($args['finish_using']) ||
	  !in_array($args['finish_using'], array_keys($this->ACTIONS)))
	$args['finish_using'] = "ROT";
      return $args;
    }

    // ------------------------------------------------------------
    // Enter finish by rotation
    // ------------------------------------------------------------
    $rotation = $this->REGATTA->getRotation();
    if (isset($args['f_places'])) {
      $race = Preferences::getObjectWithProperty($rotation->getRaces(),
						 "id",
						 $args['race']);
      if ($race == null) {
	$mes = sprintf("Invalid race (%s).", $args['race']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	unset($args['race']);
	return $args;
      }

      // Ascertain that there are as many finishes as there are sails
      // participating in this regatta (every team has a finish). Make
      // associative array of sail numbers => teams
      $sails = array();
      foreach ($this->REGATTA->getTeams() as $team)
	$sails[$rotation->getSail($race, $team)] = $team;

      $count = count($sails);
      $finishes = array();
      $now_fmt = "now + %d seconds";
      for ($i = 0; $i < $count; $i++) {

	// Verify
	if (!isset($args["p$i"])) {
	  $this->announce(new Announcement("Missing team(s).", Announcement::ERROR));
	  return $args;
	}
	$sail = $args["p$i"];
	// Possible sail
	if (!in_array($sail, array_keys($sails))) {
	  $mes = sprintf('Sail not in this race (%s).', $sail);
	  $this->announce(new Announcement($mes, Announcement::ERROR));
	  return $args;
	}
	
	$finish = new Finish(null, $race, $sails[$sail], $this->REGATTA);
	$finish->entered = new DateTime(sprintf($now_fmt, 3 * $i));
	$finishes[] = $finish;
	unset($sails[$sail]);
      }

      $this->REGATTA->setFinishes($finishes);

      // Reset
      unset($args['chosen_race']);
      $mes = sprintf("Finishes entered for race %s.", $race);
      $this->announce(new Announcement($mes));
    }

    // ------------------------------------------------------------
    // Enter finish by team
    // ------------------------------------------------------------
    if (isset($args['f_teams'])) {
      $race = Preferences::getObjectWithProperty($this->REGATTA->getRaces(),
						 "id",
						 $args['race']);
      if ($race == null) {
	$mes = sprintf("Invalid race (%s).", $args['race']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	unset($args['race']);
	return $args;
      }

      // Ascertain that each team has a stake in this finish
      $teams = array();
      foreach ($this->REGATTA->getTeams() as $team)
	$teams[$team->id] = $team;
      
      $count = count($teams);
      $finishes = array();
      $now_fmt = "now + %d seconds";
      for ($i = 0; $i < $count; $i++) {

	// Verify
	if (!isset($args["p$i"])) {
	  $this->announce(new Announcement("Missing team(s).", Announcement::ERROR));
	  return $args;
	}
	$team_id = $args["p$i"];
	// Possible team
	if (!in_array($team_id, array_keys($teams))) {
	  $mes = sprintf('Invalid team ID (%s).', $team_id);
	  $this->announce(new Announcement($mes, Announcement::ERROR));
	  return $args;
	}

	$finish = new Finish(null, $race, $teams[$team_id], $this->REGATTA);
	$finish->entered = new DateTime(sprintf($now_fmt, 3 * $i));
	$finishes[] = $finish;
	unset($teams[$team_id]);
      }

      $this->REGATTA->setFinishes($finishes);

      // Reset
      unset($args['chosen_race']);
      $mes = sprintf("Finishes entered for race %s.", $race);
      $this->announce(new Announcement($mes));

      $args['finish_using'] = "TMS";
    }
    
    return $args;
  }

  public function isActive() {
    return ($this->REGATTA->getRacesCount() > 0 &&
	    $this->REGATTA->getFleetSize() > 0);
  }
}

?>