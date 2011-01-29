<?php
/**
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @created 2009-10-04
 * @package tscore
 */

require_once('conf.php');

/**
 * Page for editing races in a regatta and their boats.
 *
 * @version 2010-07-31: Starting with this version, only the number of
 * races and division is chosen, with appropriate warning messages if
 * there are already rotations or finishes in place.
 *
 * @TODO For the boats in the race, provide a mechanism similar to rotation
 * creation, where the user can choose a range of races in each
 * division to assign a boat at a time. Provide an extra box for every
 * new division.
 */
class RacesPane extends AbstractPane {

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Edit Races", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $divisions = $this->REGATTA->getDivisions();
    $boats     = Preferences::getBoats();
    $boatOptions = array();
    foreach ($boats as $boat) {
      $boatOptions[$boat->id] = $boat->name;
    }

    //------------------------------------------------------------
    // Number of races and divisions
    // ------------------------------------------------------------
    $final = $this->REGATTA->get(Regatta::FINALIZED);
    $this->PAGE->addContent($p = new Port("Races and divisions"));
    $p->addChild($form = $this->createForm());
    $form->addChild(new FItem("Number of divisions:",
			      $f_div = new FSelect("num_divisions", array(count($divisions)))));
    $form->addChild(new FItem("Number of races:",
			      $f_rac = new FText("num_races",
						 count($this->REGATTA->getRaces(Division::A())))));
    if ($final) {
      $f_div->addAttr("disabled", "disabled");
      $f_rac->addAttr("disabled", "disabled");
    }
    elseif (count($this->REGATTA->getRotation()->getRaces()) > 0 ||
	    count($this->REGATTA->getScoredRaces()) > 0) {
      $form->addChild(new Para('<strong>Warning: </strong>Adding races or divisions to this regatta ' .
			       'will require that you also edit the rotations (if any). Removing races ' .
			       'or divisions will also remove the finishes and rotations (if any) for ' .
			       'the removed races!'));
    }
    $form->addChild(new Para('<strong>Note: </strong>Extra races are automatically removed when ' .
			     'the regatta is finalized.'));
    $form->addChild($f_sub = new FSubmit("set-races", "Set races"));
    if ($final) $f_sub->addAttr("disabled", "disabled");

    // Fill the select boxes
    for ($i = 1; $i <= 4; $i++) {
      $f_div->addChild(new Option($i, $i));
    }

    //------------------------------------------------------------
    // Edit existing boats
    
    $this->PAGE->addContent($p = new Port("Boat assignments"));
    $p->addChild(new Para("Edit the boat associated with each race. This is necessary " .
			  "at the time of entering RP information. " .
			  "Races that are not sailed are automatically removed " .
			  "when finalizing the regatta."));
    $p->addChild($form = $this->createForm());

    // Add input elements
    $form->addChild($para = new Para(""));
    $para->addChild(new FSubmit("editboats", "Edit boats"));


    // Table of races: columns are divisions; rows are race numbers
    $form->addChild($tab = new Table());
    $tab->addAttr("class", "narrow");
    $head = array(Cell::th("#"));
    $races = array();
    $max   = 0; // the maximum number of races in any given division
    foreach ($divisions as $div) {
      $head[] = Cell::th("Division " . $div);
      $races[(string)$div] = $this->REGATTA->getRaces($div);
      $max = max($max, count($races[(string)$div]));
    }
    $tab->addHeader(new Row($head));

    //  - Global boat
    $row = array(Cell::th("All"));
    foreach ($divisions as $div) {
      $c = new Cell();
      $c->addChild(new FHidden("div-value[]", $div));
      $c->addChild($f_sel = new FSelect("div-boat[]", array()));
      $f_sel->addChild(new Option("", "[Use table]"));
      $f_sel->addOptions($boatOptions);
      $row[] = $c;
    }
    $tab->addRow(new Row($row));

    //  - Table content
    for ($i = 0; $i < $max; $i++) {
      // Add row
      $row = array(Cell::th($i + 1));

      // For each division
      foreach ($divisions as $div) {
	$c = new Cell();

	if (isset($races[(string)$div][$i])) {
	  $race = $races[(string)$div][$i];
	  $c->addChild($f_sel = new FSelect($race, array($race->boat->id)));
	  $f_sel->addOptions($boatOptions);
	}

	$row[] = $c;
      }
      $tab->addRow(new Row($row));
    }
  }

  /**
   * Process edits to races for the regatta
   */
  public function process(Array $args) {
    // ------------------------------------------------------------
    // Set races
    // ------------------------------------------------------------
    if (isset($args['set-races']) && !$this->REGATTA->get(Regatta::FINALIZED)) {
      // ------------------------------------------------------------
      // Add new divisions
      //   1. Get host's preferred boat
      $hosts = $this->REGATTA->getHosts();
      $host = array_shift($hosts);
      $boat = Preferences::getPreferredBoat($host->school);
      
      $cur_divisions = $this->REGATTA->getDivisions();
      $cur_races     = count($this->REGATTA->getRaces(Division::A()));
      if (isset($args['num_divisions'])) {
	$pos_divisions = Division::getAssoc();
	$num_divisions = (int)$args['num_divisions'];
	if ($num_divisions < 1 || $num_divisions > count($pos_divisions)) {
	  $this->announce(new Announcement("Invalid number of divisions.", Announcement::ERROR));
	  return $args;
	}
	$pos_divisions_list = array_values($pos_divisions);
	for ($i = count($cur_divisions); $i < $num_divisions; $i++) {
	  $div = $pos_divisions_list[$i];
	  for ($j = 0; $j < $cur_races; $j++) {
	    $race = new Race();
	    $race->division = $div;
	    $race->boat = $boat;
	    $race->number = ($j + 1);
	    $this->REGATTA->setRace($race);
	  }
	}

	// ------------------------------------------------------------
	// Subtract extra divisions
	for ($i = count($cur_divisions); $i > $num_divisions; $i--) {
	  $this->REGATTA->removeDivision($pos_divisions_list[$i - 1]);
	}
      }

      if (isset($args['num_races'])) {
	$num_races = (int)$args['num_races'];
	if ($num_races < 1 || $num_races > 99) {
	  $this->announce(new Announcement("Invalid number of races.", Announcement::ERROR));
	  return $args;
	}
	// Add
	for ($i = $cur_races; $i < $num_races; $i++) {
	  foreach ($cur_divisions as $div) {
	    $race = new Race();
	    $race->division = $div;
	    $race->boat = $boat;
	    $race->number = ($i + 1);
	    $this->REGATTA->setRace($race);
	  }
	}

	// Remove (from the end, of course!)
	for ($i = $cur_races; $i > $num_races; $i--) {
	  foreach ($cur_divisions as $div) {
	    $race = new Race();
	    $race->division = $div;
	    $race->number = $i;
	    $this->REGATTA->removeRace($race);
	  }
	}
      }

      $this->announce(new Announcement("Set number of races."));
    }

    // ------------------------------------------------------------
    // Update boats

    $completed_divisions = array();
    if (isset($args['editboats'])) {
      unset($args['editboats']);
      $boats = Preferences::getBoats();

      // Is there an assignment for all the races in the division?
      if (isset($args['div-value']) && is_array($args['div-value']) &&
	  isset($args['div-boat'])  && is_array($args['div-boat']) &&
	  count($args['div-value']) == count($args['div-boat'])) {
	foreach ($args['div-value'] as $i => $d) {
	  try {
	    $div = Division::get($d);
	    if (!empty($args['div-boat'][$i]) &&
		($boat = Preferences::getObjectWithProperty($boats, "id", $args['div-boat'][$i])) !== null) {
	      // Assign
	      $completed_divisions[] = $div;
	      foreach ($this->REGATTA->getRaces($div) as $race) {
		$race->boat = $boat;
	      }
	    }
	  }
	  catch (Exception $e) {
	    $this->announce(new Announcement(sprintf("Invalid division (%s) chosen for boat assignment.",
						     $d),
					     Announcement::WARNING));
	  }
	}
	unset($args['div-value'], $args['div-boat']);
      }
      
      // Let the database decide whether the values are valid to begin
      // with. Just keep track of whether there were errors.
      $errors = false;
      foreach ($args as $key => $value) {
	try {
	  $race = Race::parse($key);
	  if (!in_array($race->division, $completed_divisions)) {
	    $race = $this->REGATTA->getRace($race->division, $race->number);
	    $boat = Preferences::getObjectWithProperty($boats, "id", $value);
	    $race->boat = $boat;
	  }
	}
	catch (Exception $e) {
	  $errors = true;
	}
      }

      if ($errors) {
	$mes = "Not all races updated.";
	$this->announce(new Announcement($mes, Announcement::WARNING));
      }
      else
	$this->announce(new Announcement("Updated races."));
    }
    return array();
  }

  public function isActive() { return true; }
}
?>