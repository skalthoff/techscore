<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

/**
 * Add, edit, and display individual penalties
 *
 * 2011-02-09: Allow for multiple penalty entry at a time
 *
 * @author Dayan Paez
 * @version 2010-01-25
 */
class EnterPenaltyPane extends AbstractPane {

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Add penalty", $user, $reg);
  }

  protected function fillHTML(Array $args) {

    // Default is the last scored race
    $finished_races = $this->REGATTA->getScoredRaces();
    if (count($finished_races) == 0) {
      $this->announce(new Announcement("No finishes entered.",
				       Announcement::WARNING));
      $this->redirect();
    }
    $theRace = (isset($args['p_race'])) ?
      $args['p_race'] :
      $finished_races[count($finished_races)-1];

    $p_type = null;
    if (isset($args['p_type']))
      $p_type = $args['p_type'];

    $divisions = $this->REGATTA->getDivisions();

    if ($p_type == null) {

      // ------------------------------------------------------------
      // 1. Chosen race
      // ------------------------------------------------------------
      $this->PAGE->addContent($p = new Port("1. Individual penalties and breakdowns"));
      $p->add($form = $this->createForm());
      $form->add(new FItem("Possible races:",
				$tab = new Table()));

      // Table of finished races
      $tab->set("class", "narrow");
      $row = array();
      foreach ($divisions as $div)
	$row[] = Cell::th($div);
      $tab->addHeader(new Row($row));
      $row = array();
      foreach ($divisions as $div) {
	// Get races with finishes
	$nums = array();
	foreach ($this->REGATTA->getScoredRaces($div) as $race)
	  $nums[] = $race->number;
	$row[] = new Cell(Utilities::makeRange($nums));
      }
      $tab->addRow(new Row($row));
      $form->add($fitem = new FItem("Race:", 
					 new XTextInput("p_race",
						   $theRace,
						   array("size"=>"4",
							 "maxlength"=>"4",
							 "id"=>"chosen_race",
							 "class"=>"narrow"))));

      // Penalty type
      $form->add(new FItem("Penalty type:", new Select("p_type", array($p_sel = new FOptionGroup("Penalties"),
								       $b_sel = new FOptionGroup("Breakdowns")))));
      // Penalties and breakdown options
      foreach (Penalty::getList() as $key => $val)
	$p_sel->add(new FOption($key, $val));
      foreach (Breakdown::getList() as $key => $val)
	$b_sel->add(new FOption($key, $val));

      // Submit
      $form->add(new XSubmitInput("c_race", "Next >>"));
    }
    else {
      $rotation = $this->REGATTA->getRotation();

      // ------------------------------------------------------------
      // 2. Penalty details
      // ------------------------------------------------------------
      $title = sprintf("2. %s in race %s", $p_type, $theRace);
      $this->PAGE->addContent($p = new Port($title));
      $p->add($form = $this->createForm());
      $form->add(new XHiddenInput("p_type", $p_type));
      $form->add(new FItem("Team:", $f_sel = new Select("finish[]", array(), array('multiple'=>'multiple'))));
      foreach ($this->REGATTA->getTeams() as $team) {
	$fin = $this->REGATTA->getFinish($theRace, $team);
	if ($fin->penalty === null) {
	  $id = sprintf('%s,%s', $theRace, $team->id);
	  $f_sel->add(new FOption($id, sprintf("%s (%s)",
					       $team,
					       $rotation->getSail($theRace, $team))));
	}
      }

      // - comments
      $form->add(new FItem("Comments:",
				new XTextArea("p_comments", "",
					      array("rows"=>"2",
						    "cols"=>"50"))));
      // - Amount, or average, if necessary
      $b = Breakdown::getList();
      if (in_array($p_type, array_keys($b)))
	$average = "Use average within division";
      else
	$average = "Use standard scoring (FLEET + 1).";
      $new_score = new FItem("New score:",
			     $cb = new XCheckboxInput("average", "yes", array("id"=>"avg_box")));
      $cb->set("onclick", "document.getElementById('p_amount').disabled = this.checked;document.getElementById('displace_box').disabled = this.checked;");
      // $cb->set("checked", "checked");
      $new_score->add(new XLabel("avg_box", $average));
      $form->add($new_score);

      $new_score = new FItem("OR Assign score:",
			     new XTextInput("p_amount", "", array("size"=>"2", "id"=>"p_amount")));
      $new_score->add(new XCheckboxInput("displace", "yes", array("id"=>"displace_box")));
      $new_score->add(new XLabel('displace_box', 'Displace finishes'));
      $form->add($new_score);

      // script to turn off the two by default
      $form->add($sc = new GenericElement("script"));
      $sc->set("type", "text/javascript");
      $sc->add(new XText("document.getElementById('p_amount').disabled = true;"));
      $sc->add(new XText("document.getElementById('displace_box').disabled = true;"));
      $sc->add(new XText("document.getElementById('avg_box').checked   = true;"));
    
      // Submit
      $form->add(new XSubmitInput("p_cancel", "Cancel"));
      $form->add(new XSubmitInput("p_submit", "Enter $p_type"));

      // FAQ's
      $this->PAGE->addContent($p = new Port("FAQ"));
      $fname = sprintf("%s/faq/penalty.html", dirname(__FILE__));
      $p->add(new XRawText(file_get_contents($fname)));
    }
  }

  public function process(Array $args) {

    // ------------------------------------------------------------
    // 0. Cancel
    // ------------------------------------------------------------
    if (isset($args['p_cancel'])) {
      unset($args['p_type']);
      return $args;
    }

    // ------------------------------------------------------------
    // 1. Choose race
    // ------------------------------------------------------------
    // Change of race request
    if (isset($args['c_race'])) {
      // - validate race
      $races = $this->REGATTA->getScoredRaces();
      try {
	$race = Race::parse($args['p_race']);
	$race = $this->REGATTA->getRace($race->division, $race->number);
	$theRace = Preferences::getObjectWithProperty($races, "id", $race->id);
	if ($theRace == null) {
	  $mes = sprintf("No finish recorded for race %s.", $theRace);
	  $this->announce(new Announcement($mes, Announcement::WARNING));
	  unset($args['p_race']);
	  unset($args['p_type']);
	  return $args;
	}
	$args['p_race'] = $theRace;
      }
      catch (InvalidArgumentException $e) {
	$mes = sprintf("Invalid race (%s).", $args['p_race']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	unset($args['p_race']);
	unset($args['p_type']);
	return $args;
      }

      // - validate penalty type
      if (!isset($args['p_type']) ||
	  (!in_array($args['p_type'], array_keys(Penalty::getList())) &&
	   !in_array($args['p_type'], array_keys(Breakdown::getList())))) {
	$mes = sprintf("Invalid or missing penalty (%s).", $args['p_type']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
      }
      return $args;
    }

    // ------------------------------------------------------------
    // 2. Enter penalty
    // ------------------------------------------------------------
    if (isset($args['p_submit']) ) {
      // Validate input
      if (!isset($args['finish']) || !is_array($args['finish'])) {
	$this->announce(new Announcement("Finish must be a list.", Announcement::ERROR));
	return $args;
      }
      $finishes = array();
      $teams = array();
      foreach ($args['finish'] as $f) {
	$tokens = explode(',', $f);
	if (count($tokens) != 2) {
	  $this->announce(new Announcement("Invalid finish provided ($f).", Announcement::ERROR));
	  return $args;
	}
	try {
	  $race = Race::parse($tokens[0]);
	  $race = $this->REGATTA->getRace($race->division, $race->number);
	  if ($race === null)
	    throw new InvalidArgumentException("No such race!");
	}
	catch (InvalidArgumentException $e) {
	  $this->announce(new Announcement("Invalid race for finish.", Announcement::ERROR));
	  return $args;
	}
	$team = $this->REGATTA->getTeam($tokens[1]);
	if ($team === null) {
	  $this->announce(new Announcement("Invalid team for finish.", Announcement::ERROR));
	  return $args;
	}
	$finish = $this->REGATTA->getFinish($race, $team);
	if ($finish !== null) {
	  $finishes[] = $finish;
	  $teams[] = $team;
	}
      }
      if (count($finishes) == 0) {
	$this->announce(new Announcement("No finishes for penalty/breakdown.", Announcement::ERROR));
	return $args;
      }
      $thePen  = $args['p_type'];
      $theComm = addslashes($args['p_comments']);

      // Get amount, checkbox has preference
      if (isset($args['average'])) {
	$theAmount = -1;
      }
      else if (is_numeric($args['p_amount']) &&
	       (int)($args['p_amount']) > 0) {
	$theAmount = (int)($args['p_amount']);
      }
      else {
	$mes = sprintf("Invalid penalty/breakdown amount (%s).", $args['p_amount']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return $args;
      }

      // Based on the amount, honor the displace option
      $theDisplace = 0;
      if ($theAmount > 0 && isset($args['displace'])) {
	$theDisplace = 1;
      }

      // give the users the flexibility to do things wrong, if they so choose
      $breakdowns = Breakdown::getList();
      foreach ($finishes as $theFinish) {
	if (in_array($thePen, array_keys($breakdowns))) {
	  if ($theFinish->score !== null && $theAmount >= $theFinish->score) {
	    $this->announce(new Announcement("The assigned score is no better than the actual score; ignoring.",
					     Announcement::WARNING));
	    $args['p_race'] = $race;
	    return $args;
	  }
	  $theFinish->penalty = new Breakdown($thePen, $theAmount, $theComm, $theDisplace);
	}
	else {
	  if ($theFinish->score !== null &&
	      $theAmount > 0 &&
	      $theAmount <= $theFinish->score) {
	    $this->announce(new Announcement("The assigned penalty score is no worse than their actual score; ignoring.",
					     Announcement::WARNING));
	    return $args;
	  }
	  elseif ($theAmount > ($fleet = $this->REGATTA->getFleetSize() + 1)) {
	    $this->announce(new Announcement(sprintf("The assigned penalty is greater than the maximum penalty of FLEET + 1 (%d); ignoring.", $fleet),
					     Announcement::WARNING));
	    return $args;
	  }
	  $theFinish->penalty = new Penalty($thePen, $theAmount, $theComm, $theDisplace);
	}
      }
      $this->REGATTA->runScore($race);
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
      
      $mes = sprintf("Added %s for %s.", $thePen, implode(', ', $teams));
      $this->announce(new Announcement($mes));
      unset($args['p_type']);
    }

    return $args;
  }
}