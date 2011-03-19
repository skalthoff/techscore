<?php
/**
 * This file is part of TechScore
 *
 * @package tscore
 */
require_once('conf.php');

/**
 * Pane to create the rotations
 *
 * 2011-02-18: Only one BYE team is allowed per rotation
 *
 * @author Dayan Paez
 * @created 2009-10-04
 */
class SailsPane extends AbstractPane {

  // Options for rotation types
  private $ROTS = array("STD"=>"Standard: +1 each set",
			"SWP"=>"Swap:  Odds up, evens down",
			"OFF"=>"Offset by (+/-) amount from existing division",
			"NOR"=>"No rotation");
  private $STYLES = array("navy"=>"Navy: rotate on division change",
			  "fran"=>"Franny: automatic offset",
			  "copy"=>"All divisions similar");
  private $SORT   = array("none"=>"Order as shown",
			  "num" =>"Numerically",
			  "alph"=>"Alpha-numerically");

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Setup rotations", $user, $reg);
    $this->title = "Setup";
    $this->urls[] = 'rotation';
    $this->urls[] = 'rotations';
    $this->urls[] = 'create-rotation';
    $this->urls[] = 'create-rotations';
    $this->urls[] = 'setup-rotation';
  }

  /**
   * Presents options when there are combined divisions or only one
   * division. This function takes care of only the second step in
   * creating rotations below in fillHTML
   *
   * @param Array $args the arguments
   * @see fillHTML
   */
  private function fillCombined($chosen_rot, $chosen_div) {
    
    $chosen_rot_desc = explode(":", $this->ROTS[$chosen_rot]);
    $this->PAGE->addContent($p = new Port(sprintf("2. %s for all division(s)", $chosen_rot_desc[0])));
    $p->addChild($form = $this->createForm());
    $form->addChild(new FHidden("rottype", $chosen_rot));
    
    $teams = $this->REGATTA->getTeams();
    $divisions = $this->REGATTA->getDivisions();

    // Races
    $range_races = $this->REGATTA->getCombinedUnscoredRaces();
    $form->addChild($f_item = new FItem("Races:",
					new FText("races", Utilities::makeRange($range_races),
						  array("id"=>"frace"))));
    $f_item->addChild($tab = new Table());
    $tab->addAttr("class", "narrow");
    $tab->addHeader(new Row(array(Cell::th("Possible"))));
    $tab->addRow(new Row(array(new Cell(Utilities::makeRange($range_races),
					array("id"=>"range_races")))));

    // Set size
    $form->addChild(new FItem('Races in set:<br/><small>With "no rotation", value is ignored</small>',
			      $f_text = new FText("repeat", 2)));
    $f_text->addAttr("size", 2);
    $f_text->addAttr("id",  "repeat");

    // Teams table
    $bye_team = null;
    if (count($divisions) * count($teams) % 2 > 0) {
      $bye_team = new ByeTeam();
      $form->addChild(new Para("Swap divisions require an even number of total teams at the time of creation. If you choose swap division, TechScore will add a \"BYE Team\" as needed to make the total number of teams even. This will produce an unused boat in every race."));
    }
    $form->addChild(new FItem("Enter sail numbers in first race:",
			      $tab = new Table()));
    $tab->addAttr("class", "narrow");

    $i = 1;
    if (count($divisions) == 1) {
      foreach ($teams as $team) {
	$name = sprintf("%s,%s", $divisions[0], $team->id);
	$tab->addRow(new Row(array(Cell::th($team),
				   new Cell(new FText($name, $i++,
						      array("size"=>"2",
							    "maxlength"=>"8",
							    "class"=>"small"))))));
      }
      if ($bye_team !== null)
	$tab->addRow(new Row(array(Cell::th($bye_team),
				   new Cell(new FText($bye_team->id, $i++,
						      array("size"=>"2",
							    "maxlength"=>"8",
							    "class"=>"small"))))));
    }
    else {
      $num_teams = count($teams);
      $tab->addHeader($row = new Row(array(Cell::th("Team"))));
      foreach ($divisions as $div)
	$row->addChild(Cell::th("Div. $div"));
      foreach ($teams as $team) {
	$tab->addRow($row = new Row(array(new Cell($team))));
	$off = 0;
	foreach ($divisions as $div) {
	  $num = $i + $off * $num_teams;
	  $name = sprintf("%s,%s", $div, $team->id);
	  $row->addChild(new Cell(new FText($name, $num, array("size"=>"2",
							       "class"=>"small",
							       "maxlength"=>"8"))));
	  $off++;
	}
	$i++;
      }
      // add bye team, if necessary
      if ($bye_team !== null) {
	$num = $i + ($off - 1) * $num_teams;
	$tab->addRow($row = new Row(array(new Cell($bye_team))));
	$row->addChild(new Cell(new FText($bye_team->id, $num, array("size"=>"2",
								     "class"=>"small",
								     "maxlength"=>"8"))));
	for ($i = 1; $i < count($divisions); $i++) {
	  $row->addChild(new Cell());
	}
      }
    }

    // order
    $form->addChild(new FItem("Order sails in first race:",
			      $f_sel = new FSelect("sort", array("num"))));
    $f_sel->addOptions($this->SORT);

    // Submit form
    $form->addChild(new FSubmit("restart",   "<< Start over"));
    $form->addChild(new FSubmit("createrot", "Create rotation"));
  }

  /**
   * Fills the HTML body, accounting for combined divisions, etc
   *
   */
  protected function fillHTML(Array $args) {

    $divisions = $this->REGATTA->getDivisions();
    $combined = ($this->REGATTA->get(Regatta::SCORING) == Regatta::SCORING_COMBINED ||
		 count($divisions) == 1);

    // Listen to requests
    $chosen_rot = (isset($args['rottype'])) ?
      $args['rottype'] : null;

    $chosen_div = $divisions;
    if (isset($args['division']) && is_array($args['division'])) {
      try {
	$chosen_div = array();
	foreach ($args['division'] as $div)
	  $chosen_div[$div] = Division::get($div);
      } catch (Exception $e) {
	$this->announce(new Announcement("Invalid division(s) specified. Using all.", Announcement::WARNING));
	$chosen_div = $divisions;
      }
    }

    $repeats = 2;
    if (isset($args['repeat']) && $args['repeat'] >= 1)
      $repeats = (int)$args['repeat'];

    // Edittype
    $edittype = (isset($args['edittype']))
      ? $args['edittype'] : "ADD";

    // Range of races
    $range_races = $this->REGATTA->getCombinedUnscoredRaces($chosen_div);

    // Existing divisions with rotations
    // Get divisions to choose from
    $rotation = $this->REGATTA->getRotation();
    
    $exist_div = $rotation->getDivisions();
    if (count($exist_div) == 0)
      $exist_div = array();
    else
      $exist_div = array_combine($exist_div, $exist_div);

    // Get signed in teams
    $p_teams = $this->REGATTA->getTeams();

    // ------------------------------------------------------------
    // 1. Choose a rotation type: SWAP rotations are allowed due to
    // the presence of a possible BYE team. Because of this, even for
    // combined scoring, the user must be given the choice of rotation
    // to use FIRST, which is this step here.
    // ------------------------------------------------------------
    if ($chosen_rot === null) {
      $this->PAGE->addContent($p = new Port("1. Create a rotation"));
      $p->addChild($form = $this->createForm());
      $form->addAttr("id", "sail_setup");
      $form->addChild(new Para("Swap divisions require an even number of total teams at the time of creation. If you choose swap division, TechScore will add a \"BYE Team\" as needed to make the total number of teams even. This will produce an unused boat in every race."));

      $form->addChild(new FItem("Type of rotation:",
				$f_sel = new FSelect("rottype", array($chosen_rot))));

      $the_rots = $this->ROTS;
      if (count($exist_div) == 0)
	unset($the_rots["OFF"]);
      $f_sel->addOptions($the_rots);

      // No need for this choice if combined
      if (!$combined) {
	$form->addChild(new FItem("Divisions to affect:",
				  $f_sel = new FSelect("division[]", $chosen_div,
						       array("multiple"=>"multiple"))));

	$div_opts = array();
	foreach ($divisions as $div)
	  $div_opts[(string)$div] = (string)$div;
	$f_sel->addOptions($div_opts);
      }
      $form->addChild(new FSubmit("choose_rot", "Next >>"));
    }

    // ------------------------------------------------------------
    // 2. Starting sails
    // ------------------------------------------------------------
    else {
      // This part is inherently different for combined
      if ($combined) {
	$this->fillCombined($chosen_rot, $chosen_div);
	return;
      }

      // Divisions
      $chosen_rot_desc = explode(":", $this->ROTS[$chosen_rot]);
      $this->PAGE->addContent($p = new Port(sprintf("2. %s for Div. %s",
						    $chosen_rot_desc[0],
						    implode(", ", $chosen_div))));
      $p->addHelp("node13.html");
      $p->addChild($form = $this->createForm());

      $form->addChild(new FHidden("rottype", $chosen_rot));
      foreach ($chosen_div as $div) {
	$form->addChild(new FHidden("division[]", $div));
      }

      // Suggest Navy/Franny special
      if (count($chosen_div) > 1 &&
	  $chosen_rot != "NOR" &&
	  $chosen_rot != "OFF") {
	$form->addChild($f_item = new FItem("Style:",
					    $f_sel = new FSelect("style",
								 array("copy"))));
	$f_sel->addOptions($this->STYLES);
      }
      else {
	$form->addChild(new FHidden("style", "copy"));
      }

      // Races
      $form->addChild($f_item = new FItem("Races:",
					  new FText("races", Utilities::makeRange($range_races),
						    array("id"=>"frace"))));
      $f_item->addChild($tab = new Table());
      $tab->addAttr("class", "narrow");
      $tab->addHeader(new Row(array(Cell::th("Possible"))));
      $tab->addRow(new Row(array(new Cell(Utilities::makeRange($range_races),
					  array("id"=>"range_races")))));

      // For Offset rotations, print only the 
      // current divisions for which there are rotations entered
      // and the offset amount
      if ($chosen_rot == "OFF") {
	$form->addChild(new FItem("Copy rotation from:",
				  $f_sel = new FSelect("from_div", array())));
	$f_sel->addOptions($exist_div);
	$form->addChild(new FItem("Amount to offset (+/-):",
				  new FText("offset", (int)(count($p_teams) / count($exist_div)),
					    array("size"=>"2",
						  "maxlength"=>"2"))));

	$form->addChild(new FSubmit("restart",   "<< Start over"));
	$form->addChild(new FSubmit("offsetrot", "Offset"));
      }
      else {
	if ($chosen_rot != "NOR") {
	  $form->addChild(new FItem("Races in set:",
				    $f_text = new FText("repeat", $repeats,
							array("size"=>"2",
							      "id"=>"repeat"))));
	}
	$divs = array_values($chosen_div);
	$form->addChild(new FItem(sprintf("Enter sail numbers in first " .
					  "race of div. <strong>%s</strong>:",
					  $divs[0]),
				  $tab = new Table()));
	$tab->addAttr("class", "narrow");

	// require a BYE team if the total number of teams
	// (divisions * number of teams) is not even
	if ($chosen_rot == "SWP" && count($p_teams) * count($divs) % 2 > 0)
	  $p_teams[] = new ByeTeam();
	$i = 1;
	foreach ($p_teams as $team) {
	  $tab->addRow(new Row(array(Cell::th($team),
				     new Cell(new FText($team->id, $i++,
							array("size"=>"2",
							      "class"=>"small",
							      "maxlength"=>"8"))))));
	}

	// order
	$form->addChild(new FItem("Order sails in first race:",
				  $f_sel = new FSelect("sort", array("num"))));
	$f_sel->addOptions($this->SORT);

	// Submit form
	$form->addChild(new FSubmit("restart",   "<< Start over"));
	$form->addChild(new FSubmit("createrot", "Create rotation"));
      }

      // FAQ's
      $this->PAGE->addContent($p = new Port("FAQ"));
      $fname = sprintf("%s/faq/sail.html", dirname(__FILE__));
      $p->addChild(new Text(file_get_contents($fname)));
    }
  }

  /**
   * Sets up rotation in the case of combined divisions or only one
   * division. Note that the rotation type and divisions must already
   * have been chosen
   *
   * @param Array $args the arguments
   * @param Array the processed arguments
   */
  private function processCombined(Array $args, $rottype) {

    // validate races
    $divisions = $this->REGATTA->getDivisions();
    $races = null;
    if (isset($args['races']) &&
	($races = Utilities::parseRange($args['races'])) != null &&
	sort($races)) {

      $races = array_intersect($races, $this->REGATTA->getCombinedUnscoredRaces());
    }
    else {
      $mes = "Could not parse range of races.";
      $this->announce(new Announcement($mes, Announcement::ERROR));
      return $args;
    }
    if (count($races) == 0) {
      $mes = "No races for which to setup rotations.";
      $this->announce(new Announcement($mes, Announcement::ERROR));
      return $args;
    }

    $rotation = $this->REGATTA->getRotation();

    // ------------------------------------------------------------
    // Create the rotation
    // ------------------------------------------------------------
    // validate repeats
    $repeats = null;
    if ($rottype === "NOR") {
      $repeats = count($divisions) * count($races);
    }
    elseif (isset($args['repeat']) && is_numeric($args['repeat'])) {
      $repeats = (int)($args['repeat']);
      if ($repeats < 1) {
	$mes = sprintf("Changed repeats to 1 from %d.", $repeats);
	$this->announce(new Announcement($mes, Announcement::WARNING));
	$repeats = 1;
      }
    }
    else {
      $mes = "Invalid or missing value for repeats.";
      $this->announce(new Announcement($mes, Announcement::ERROR));
      return $args;
    }

    // validate teams
    $keys = array_keys($args);
    $sails = array();
    $divs  = array();                      // keep track of divisions
    $tlist = array();                      // keep track of teams for multisorting
    $teams = $this->REGATTA->getTeams();
    $missing = array();
    foreach ($divisions as $div) {
      foreach ($teams as $team) {
	$id = sprintf("%s,%s", $div, $team->id);
	if (in_array($id, $keys) &&
	    !empty($args[$id])) {
	  $sails[] = $args[$id];
	  $tlist[] = $team;
	  $divs[]  = $div;
	}
	else {
	  $missing[] = sprintf("%s in Division %s", $team, $div);
	}
      }
    }
    if (count($missing) > 0) {
      $mes = sprintf("Missing team or sail for %s.", implode(", ", $missing));
      $this->announce(new Announcement($mes, Announcement::ERROR));
      return $args;
    }
    // require BYE team, when applicable
    if ($rottype == "SWP" && count($divisions) * count($teams) % 2 > 0) {
      $team = new ByeTeam();
      if (!isset($args[$team->id])) {
	$mes = "Missing BYE team.";
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return $args;
      }
      $sails[] = $args[$team->id];
      $tlist[] = $team;
      $divs[]  = Division::A();
    }

    // 3c. sorting
    $sort = "none";
    if (isset($args['sort']) && in_array($args['sort'], array_keys($this->SORT)))
      $sort = $args['sort'];
    switch ($sort) {
    case "num":
      array_multisort($sails, $tlist, SORT_NUMERIC);
      break;

    case "alph":
      array_multisort($sails, $tlist, SORT_STRING);
      break;
    }
      
    switch ($rottype) {
    case "STD":
    case "NOR":
      $rotation->createStandard($sails, $tlist, $divs, $races, $repeats);
    break;

    case "SWP":
      $rotation->createSwap($sails, $tlist, $divs, $races, $repeats);
      break;

    default:
      $mes = "Unsupported rotation type.";
      $this->announce(new Announcement($mes, Announcement::ERROR));
      return $args;
    }

    // reset
    $rotation->commit();
    UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
    $this->announce(new Announcement("New rotation successfully created."));
    unset($args['rottype']);
    return $args;
  }

  /**
   * Sets up rotations according to requests. The request for creating
   * a new rotation should include:
   * <dl>
   *   <dt>
   *
   * </dl>
   */
  public function process(Array $args) {

    // ------------------------------------------------------------
    // Reset
    // ------------------------------------------------------------
    if (isset($args['restart'])) {
      unset($args['rottype']);
      return $args;
    }

    $rottype = null;
    // ------------------------------------------------------------
    // 0. Validate inputs
    // ------------------------------------------------------------
    //   a. validate rotation
    if (isset($args['rottype']) &&
	$this->validateRotation($args['rottype'])) {
      $rottype = $args['rottype'];
    }
    else {
      $mes = "Invalid or missing rotation type.";
      $this->announce(new Announcement($mes, Announcement::ERROR));
      return array();
    }

    $combined = (count($this->REGATTA->getDivisions()) == 1 ||
		 $this->REGATTA->get(Regatta::SCORING) == Regatta::SCORING_COMBINED);

    //   b. validate division, only if not combined division
    if (!$combined) {
      $divisions = null;
      if (isset($args['division'])    &&
	  is_array($args['division']) &&
	  $this->validateDivisions($args['division'])) {
	$divisions = array();
	$div_string = array();
	foreach ($args['division'] as $div) {
	  $divisions[] = new Division($div);
	  $div_string[] = $div;
	}
	$args['division'] = $div_string;
	unset($div_string);
      }
      else {
	$mes = "Invalid or missing division[s].";
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return array();
      }
    }
    
    // ------------------------------------------------------------
    // 1. Choose rotation
    // ------------------------------------------------------------
    if (isset($args['choose_rot'])) return $args;


    // ------------------------------------------------------------
    // 2. Validate other variables
    // ------------------------------------------------------------
    // call for combined helper method
    if ($combined)
      return $this->processCombined($args, $rottype);

    //   c. validate rotation style
    $style = null;
    if (isset($args['style']) &&
	in_array($args['style'], array_keys($this->STYLES))) {
      $style = $args['style'];
    }
    else {
      $mes = "Invalid or missing rotation style.";
      $this->announce(new Announcement($mes, Announcement::ERROR));
      return $args;
    }

    //   d. validate races
    $races = null;
    if (isset($args['races']) &&
	($races = Utilities::parseRange($args['races'])) !== null &&
	sort($races)) {
      
      // keep only races that are unscored
      $races_copy = $races;
      foreach ($divisions as $div) {
	$valid_races = array();
	foreach ($this->REGATTA->getUnscoredRaces($div) as $r)
	  $valid_races[] = $r->number;
	$races = array_intersect($races, $valid_races);
      }

      // Output message about ignored races
      if (count($diff = array_diff($races_copy, $races)) > 0) {
	$mes = sprintf("Ignored races %s in divisions %s.",
		       Utilities::makeRange($diff),
		       implde(", ", $divisions));
	$this->announce(new Announcement($mes, Announcement::WARNING));
      }
      unset($races_copy, $diff);
    }
    else {
      $mes = "Could not parse range of races.";
      $this->announce(new Announcement($mes, Announcement::ERROR));
      return $args;
    }

    $rotation = $this->REGATTA->getRotation();

    // ------------------------------------------------------------
    // 3. Create new rotation
    // ------------------------------------------------------------
    if (isset($args['createrot'])) {

      // 3a. validate repeats
      $repeats = null;
      if ($rottype === "NOR") {
	$repeats = count($divisions) * count($races);
      }
      elseif (isset($args['repeat']) && is_numeric($args['repeat'])) {
	$repeats = (int)($args['repeat']);
	if ($repeats < 1) {
	  $mes = sprintf("Changed repeats to 1 from %d.", $repeats);
	  $this->announce(new Announcement($mes, Announcement::WARNING));
	  $repeats = 1;
	}
      }
      else {
	$mes = "Invalid or missing value for repeats.";
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return $args;
      }

      // 3b. validate teams: every signed-in team must exist
      $keys  = array_keys($args);
      $sails = array();
      $teams = $this->REGATTA->getTeams();
      $missing = array();
      foreach ($teams as $team) {
	$id = $team->id;
	if (isset($args[$id]) && !empty($args[$id])) {
	  $sails[] = $args[$id];
	}
	else {
	  $missing[] = (string)$team;
	}
      }
      // Add BYE team if requested
      if (isset($args['BYE'])) {
	$teams[] = new ByeTeam();
	$sails[] = $args['BYE'];
      }
      if (count($missing) > 0) {
	$mes = sprintf("Missing team or sail for %s.", implode(", ", $missing));
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return $args;
      }

      // 3c. sorting
      $sort = "none";
      if (isset($args['sort']) && in_array($args['sort'], array_keys($this->SORT)))
	$sort = $args['sort'];
      switch ($sort) {
      case "num":
	array_multisort($sails, $teams, SORT_NUMERIC);
	break;

      case "alph":
	array_multisort($sails, $teams, SORT_STRING);
	break;
      }
      
      // Arrange the races in order according to repeats and rotation
      // style. If the style is franny, then use only the first division
      // for rotation, and offset it to get the others.
      
      // ------------------------------------------------------------
      //   3-1 Franny-style rotations
      // ------------------------------------------------------------
      if ($style === "fran") {
	$offset = (int)(count($teams) / count($divisions));
	
	$template = array_shift($divisions);
	$ordered_races = $races;
	$ordered_divs  = array();
	foreach ($races as $num)
	  $ordered_divs[] = $template;

	// Perform template rotation
	switch ($rottype) {
	case "STD":
	case "NOR":
	  $rotation->createStandard($sails, $teams, $ordered_divs, $ordered_races, $repeats);
	break;

	case "SWP":
	  // ascertain that there are an even number of teams
	  if (count($teams) % 2 > 0) {
	    $mes = "There must be an even number of teams for swap rotation.";
	    $this->announce(new Announcement($mes, Announcement::ERROR));
	    return $args;
	  }
	  $rotation->createSwap($sails, $teams, $ordered_divs, $ordered_races, $repeats);
	  break;

	default:
	  $mes = "Unsupported rotation type.";
	  $this->announce(new Announcement($mes, Announcement::ERROR));
	  return $args;
	}

	// Offset subsequent divisions
	$num_teams = count($teams);
	$index = 0;
	foreach ($divisions as $div) {
	  $rotation->createOffset($template,
				  $div,
				  $races,
				  $offset * (++$index));
	}

	// Reset
	$this->announce(new Announcement("Franny-style rotation successfully created."));
	unset($args);
	return $args;
      }

      // ------------------------------------------------------------
      //   3-2 Other styles
      // ------------------------------------------------------------
      $ordered_races = array();
      $ordered_divs  = array();
      $racei = 0;
      while ($racei < count($races)) {
	foreach ($divisions as $div) {
	  $repi = 0;
	  while ($repi < $repeats && ($racei + $repi) < count($races)) {
	    $ordered_races[] = $races[$racei + $repi];
	    $ordered_divs[]  = $div;
	    $repi++;
	  }
	}
	$racei += $repeats;
      }

      // With copy style, the "set" includes all divisions
      if ($style == "copy") $repeats *= count($divisions);

      // Perform rotation
      switch ($rottype) {
      case "STD":
      case "NOR":
	$rotation->createStandard($sails, $teams, $ordered_divs, $ordered_races, $repeats);
      break;

      case "SWP":
	// ascertain that there are an even number of teams
	if (count($teams) % 2 > 0) {
	  $mes = "There must be an even number of teams for swap rotation.";
	    $this->announce(new Announcement($mes, Announcement::ERROR));
	  return $args;
	}
	$rotation->createSwap($sails, $teams, $ordered_divs, $ordered_races, $repeats);
	break;
	
      default:
	$mes = "Unsupported rotation type.";
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return $args;
      }

      // Reset
      $rotation->commit();
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      $this->announce(new Announcement("New rotation successfully created."));
      unset($args['rottype']);
    }

    // ------------------------------------------------------------
    // 4. Offset rotation
    // ------------------------------------------------------------
    if (isset($args['offsetrot'])) {

      // 4a. validate FROM division
      $exist_div = $rotation->getDivisions();
      if (count($exist_div) == 0)
	$exist_div = array();
      else
	$exist_div = array_combine($exist_div, $exist_div);

      if (isset($args['from_div']) &&
	  in_array($args['from_div'], $exist_div)) {
	$from_div = new Division($args['from_div']);
      }
      else {
	$mes = sprintf("Invalid division to offset from (%s).", $args['from_div']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return $args;
      }

      // 4b. validate offset amount
      if (isset($args['offset']) &&
	  is_numeric($args['offset'])) {
	$offset = (int)($args['offset']);
      }
      else {
	$mes = sprintf("Invalid offset amount (%s)", $args['offset']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return $args;
      }

      $num_teams = count($this->REGATTA->getTeams());
      foreach ($divisions as $div) {
	$rotation->createOffset($from_div,
				$div,
				$races,
				$offset);
      }

      // Reset
      unset($args['rottype']);
      $this->announce(new Announcement('Offset rotation created.'));
      // notify();
    }

    return $args;
  }

  // Helper methods

  /**
   * Validates the rotation string
   *
   * @param String $str the rotation key
   * @return Boolean whether the key is valid
   */
  private function validateRotation($rot) {
    if (in_array($rot, array_keys($this->ROTS)))
      return true;
    return false;
  }

  /**
   * Validates the division string
   *
   * @param String $str the rotation key
   * @return Boolean whether the key is valid
   */
  private function validateDivisions($divs) {
    $actual_divs = array();
    foreach ($this->REGATTA->getDivisions() as $div)
      $actual_divs[] = (string)$div;
    
    foreach ($divs as $d) {
      if (!in_array($d, $actual_divs))
	return false;
    }
    return true;
  }

  public function isActive() {
    return (count($this->REGATTA->getRaces()) > 0 &&
	    count($this->REGATTA->getTeams()) > 1);
  }
}
?>