<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('conf.php');

/**
 * Compares up to three sailors head to head across a season or more,
 * and include only the finishing record.
 *
 * @author Dayan Paez
 * @version 2011-03-29
 * @see CompareSailorsByRace
 */
class CompareHeadToHead extends AbstractUserPane {
  /**
   * Creates a new pane
   */
  public function __construct(User $user) {
    parent::__construct("Compare sailors head to head", $user);
  }

  private function doSailors(Array $args) {
    if (isset($args['sailor'])) {
      if (!is_array($args['sailor'])) {
	$this->announce(new Announcement("Invalid parameter given for comparison.", Announcement::ERROR));
	return false;
      }
      $list = $args['sailor'];
    }
    elseif (isset($args['sailors']))
      $list = explode(',', (string)$args['sailors']);

    require_once('mysqli/DB.php');
    DBME::setConnection(Preferences::getConnection());
    // get sailors
    $sailors = array();
    foreach ($list as $id) {
      $sailor = DBME::get(DBME::$SAILOR, $id);
      if ($sailor !== null && $sailor->icsa_id !== null)
	$sailors[] = $sailor;
      else
	$this->PAGE->addAnnouncement(new Announcement("Invalid sailor id given ($id). Ignoring.", Announcement::WARNING));
    }
    if (count($sailors) < 2) {
      $this->announce(new Announcement("Need at least two valid sailors for comparison.", Announcement::ERROR));
      return false;
    }

    // seasons. If none provided, choose the default
    $conds = array();
    if (isset($args['seasons']) && is_array($args['seasons'])) {
      foreach ($args['seasons'] as $s) {
	if (($season = DBME::parseSeason($s)) !== null)
	  $conds[] = new MyCond('season', (string)$season);
      }
    }
    else {
      $now = new DateTime();
      $season = DMBE::getSeason($now);
      $conds[] = new MyCond('season', (string)$season);
      if ($season->season == Dt_Season::SPRING) {
	$now->setDate($now->format('Y') - 1, 10, 1);
	$season = DBME::getSeason($now);
	$conds[] = new MyCond('season', (string)$season);
      }
    }
    if (count($conds) == 0) {
      $this->announce(new Announcement("There are no seasons provided for comparison.", Announcement::ERROR));
      return false;
    }

    // get first sailor's participation (dt_rp objects)
    $first_sailor = array_shift($sailors);
    $regatta_cond = DBME::prepGetAll(DBME::$REGATTA, new MyBoolean($conds, MyBoolean::mOR));
    $regatta_cond->fields(array('id'), DBME::$REGATTA->db_name());
    $team_cond = DBME::prepGetAll(DBME::$TEAM, new MyCondIn('regatta', $regatta_cond));
    $team_cond->fields(array('id'), DBME::$TEAM->db_name());
    $dteam_cond = DBME::prepGetAll(DBME::$TEAM_DIVISION, new MyCondIn('team', $team_cond));
    $dteam_cond->fields(array('id'), DBME::$TEAM_DIVISION->db_name());
    $first_rps = DBME::getAll(DBME::$RP, new MyBoolean(array(new MyCond('sailor', $first_sailor->id),
							     new MyCondIn('team_division', $dteam_cond))));

    // (reg_id => (division => (sailor_id => <rank races>)))
    $table = array();
    $regattas = array();
    foreach ($first_rps as $rp) {
      if (!isset($table[$rp->team_division->team->regatta->id])) {
	$table[$rp->team_division->team->regatta->id] = array();
	$regattas[$rp->team_division->team->regatta->id] = $rp->team_division->team->regatta;
      }
      if (!isset($table[$rp->team_division->team->regatta->id][$rp->team_division->division]))
	$table[$rp->team_division->team->regatta->id][$rp->team_division->division] = array();

      $rank = sprintf('%d%s', $rp->team_division->rank, $rp->team_division->division);
      if (count($rp->race_nums) != $rp->team_division->team->regatta->num_races)
	$rank .= sprintf(' (%s)', Utilities::makeRange($rp->race_nums));
      $table[$rp->team_division->team->regatta->id][$rp->team_division->division][$rp->sailor->id] = $rank;
    }

    // Go through each of the remaining sailors, keeping only the
    // regatta and divisions which they have in common.
    foreach ($sailors as $sailor) {
      $copy = $table;
      foreach ($copy as $rid => $divs) {
	foreach ($divs as $div => $dteams) {
	  $rps = $regattas[$rid]->getParticipation($sailor, $div, Dt_Rp::SKIPPER);
	  if (count($rps) == 0)
	    unset($table[$rid][$div]);
	  else {
	    $rank = sprintf('%d%s', $rps[0]->team_division->rank, $div);
	    if (count($rp->race_nums) != $regattas[$rid]->num_races)
	      $rank .= sprintf(' (%s)', Utilities::makeRange($rp->race_nums)); 
	    $table[$rid][$div][$sailor->id] = $rank;
	  }
	}
	// Is there anything left for this RID?
	if (count($table[$rid]) == 0) {
	  unset($table[$rid]);
	  unset($regattas[$rid]);
	}
      }
    }

    // are there any regattas in common?
    if (count($table) == 0) {
      $this->announce(new Announcement(sprintf("The sailors provided (%s, %s) have not sailed head to head in any division in any of the regattas in the seasons specified.", $first_sailor, implode(", ", $sailors)), Announcement::WARNING));
      return false;
    }

    // push the sailor back
    array_unshift($sailors, $first_sailor);
    $this->PAGE->addContent($p = new Port("Compare sailors head-to-head"));
    $p->addChild($tab = new Table());
    $tab->addHeader($row = new Row(array(Cell::th("Regatta"), Cell::th("Season"))));
    foreach ($sailors as $sailor)
      $row->addChild(Cell::th($sailor));
    foreach ($table as $rid => $divs) {
      foreach ($divs as $list) {
	$tab->addRow($row = new Row(array(new Cell($regattas[$rid]->name), new Cell($regattas[$rid]->season))));
	foreach ($sailors as $sailor)
	  $row->addChild(new Cell($list[$sailor->id]));
      }
    }
    return true;
  }

  public function fillHTML(Array $args) {
    // Look for sailors as an array named 'sailors'
    if (isset($args['sailor']) || isset($args['sailors'])) {
      if ($this->doSailors($args))
	return;
      WebServer::go('/compare-sailors');
    }

    // ------------------------------------------------------------
    // Provide an input box to choose sailors using AJAX
    // ------------------------------------------------------------
    $this->PAGE->addHead(new GenericElement('link', array(new Text("")),
					    array('type'=>'text/css',
						  'href'=>'/inc/css/aa.css',
						  'rel'=>'stylesheet')));
    $this->PAGE->addHead(new GenericElement('script', array(new Text("")), array('src'=>'/inc/js/aa.js')));
    $this->PAGE->addContent(new Para("Use this form to compare sailors head-to-head, showing the regattas that the sailors have sailed in common, and printing their place finish for each."));
    $this->PAGE->addContent($form = new Form('/compare-sailors', "get"));

    // Season selection
    $form->addChild($p = new Port("Seasons to compare"));
    $p->addChild(new Para("Choose at least one season to compare from the list below, then choose the sailors in the next panel."));
    $p->addChild($ul = new Itemize());
    $ul->addAttr('style', 'list-style-type:none');

    $now = new Season(new DateTime());
    $then = null;
    if ($now->getSeason() == Season::SPRING)
      $then = Season::parse(sprintf('f%0d', ($now->getTime()->format('Y') - 1)));
    foreach (Preferences::getActiveSeasons() as $season) {
      $ul->addChild($li = new LItem());
      $li->addChild($chk = new FCheckbox('seasons[]', $season, array('id' => $season)));
      $li->addChild(new Label($season, $season->fullString()));

      if ((string)$season == (string)$now || (string)$season == (string)$then)
	$chk->addAttr('checked', 'checked');
    }

    // Sailor search
    $form->addChild($p = new Port("New sailors"));
    $p->addChild(new GenericElement('noscript', array(new Para("Right now, you need to enable Javascript to use this form. Sorry for the inconvenience, and thank you for your understanding."))));
    $p->addChild(new FItem('Name:', $search = new FText('name-search', "")));
    $search->addAttr('id', 'name-search');
    $p->addChild($ul = new Itemize());
    $ul->addAttr('id', 'aa-input');
    $ul->addItems(new LItem("No sailors.", array('class' => 'message')));
    $form->addChild(new FSubmit('set-sailors', "Compare sailors"));
  }

  public function process(Array $args) {
    return false;
  }
}
?>