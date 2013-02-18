<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

/**
 * Provide visual organization of teams to rank, applicable to team
 * racing only.
 *
 * 2013-01-10: Provide interface for ignoring races from record
 *
 * @author Dayan Paez
 * @version 2013-01-05
 */
class RankTeamsPane extends AbstractPane {
  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Rank teams", $user, $reg);
    if ($reg->scoring != Regatta::SCORING_TEAM)
      throw new SoterException("Pane only available for team racing regattas.");
  }

  protected function fillTeam(Team $team) {
    require_once('regatta/Rank.php');

    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/team-rank.js')));
    $this->PAGE->addContent($p = new XPort("Race record for " . $team));
    $p->add($f = $this->createForm());

    $divisions = $this->REGATTA->getDivisions();
    $races = $this->REGATTA->getRacesForTeam(Division::A(), $team);
    $f->add(new XP(array(), "Use this pane to specify which races should be accounted for when creating the overall win-loss record for " . $team . ". Greyed-out races are currently being ignored."));

    $rows = array(); // the row WITHIN the table
    $cells = array(); // cells for a given row, indexed by team name
    $records = array(); // the TeamRank object for the given round
    $recTDs = array(); // the record cell for each table
    foreach ($races as $race) {
      $fr_finishes = $this->REGATTA->getFinishes($race);
      if (count($fr_finishes) == 0)
	continue;

      $finishes = array();
      foreach ($fr_finishes as $finish)
        $finishes[] = $finish;
      for ($i = 1; $i < count($divisions); $i++) {
        foreach ($this->REGATTA->getFinishes($this->REGATTA->getRace($divisions[$i], $race->number)) as $finish)
          $finishes[] = $finish;
      }

      if (!isset($rows[$race->round->id])) {
	$records[$race->round->id] = new TeamRank($team);
	$recTDs[$race->round->id] = new XTD(array('class'=>'rank-record'), "");
	$rows[$race->round->id] = new XTR(array(), array($recTDs[$race->round->id], new XTH(array(), $team)));
        $cells[$race->round->id] = array();

	$f->add(new XH3("Round: " . $race->round));
	$f->add(new XTable(array('class'=>'rank-table'), array($rows[$race->round->id])));
      }

      $row = $rows[$race->round->id];
      $record = $records[$race->round->id];

      $myScore = 0;
      $theirScore = 0;
      foreach ($finishes as $finish) {
	if ($finish->team->id == $team->id)
	  $myScore += $finish->score;
	else
	  $theirScore += $finish->score;
      }
      if ($myScore < $theirScore) {
	$className = 'rank-win';
	$display = 'W';
	if ($race->tr_ignore === null)
	  $record->wins++;
      }
      elseif ($myScore > $theirScore) {
	$className = 'rank-lose';
	$display = 'L';
	if ($race->tr_ignore === null)
	  $record->losses++;
      }
      else {
	$className = 'rank-tie';
	$display = 'T';
	if ($race->tr_ignore === null)
	  $record->ties++;
      }
      $display .= sprintf(' (%s)', $myScore);
      $other_team = $race->tr_team1;
      if ($other_team->id == $team->id)
	$other_team = $race->tr_team2;
      $id = sprintf('r-%s', $race->id);
      $cell = new XTD(array(),
                      array($chk = new XCheckboxInput('race[]', $race->id, array('id'=>$id, 'class'=>$className)),
                            $label = new XLabel($id, $display . " vs. ")));
      $cells[$race->round->id][(string)$other_team] = $cell;

      $label->add(new XBr());
      $label->add(sprintf('%s (%s)', $other_team, $theirScore));
      $label->set('class', $className);
      if ($race->tr_ignore === null)
	$chk->set('checked', 'checked');
    }

    // add all the rows
    foreach ($cells as $id => $list) {
      ksort($list);
      foreach ($list as $cell)
        $rows[$id]->add($cell);
    }

    // update all recTDs
    foreach ($records as $id => $record)
      $recTDs[$id]->add($record->getRecord());

    $f->add($p = new XSubmitP('set-records', "Set race records"));
    $p->add(new XHiddenInput('team', $team->id));
    $p->add(" ");
    $p->add(new XA($this->link('rank'), "Return to rank list"));
  }

  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Specific team?
    // ------------------------------------------------------------
    if (isset($args['team'])) {
      if (($team = $this->REGATTA->getTeam($args['team'])) === null) {
	Session::pa(new PA("Invalid team chosen.", PA::E));
	$this->redirect('rank');
      }
      $this->fillTeam($team);
      return;
    }

    // ------------------------------------------------------------
    // All ranks
    // ------------------------------------------------------------
    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tablesort.js'));
    $this->PAGE->addContent(new XP(array(), "Use this pane to set the rank for the teams in the regatta. By default, teams are ranked by the system according to win percentage, but tie breaks must be broken manually."));
    $this->PAGE->addContent(new XP(array(), "To edit a particular team's record by setting which races count towards their record, click on the win-loss record for that team. Remember to click \"Set ranks\" to save the order before editing a team's record."));

    $this->PAGE->addContent($f = $this->createForm());
    $f->add($tab = new XQuickTable(array('id'=>'divtable', 'class'=>'teamtable narrow'),
                                   array("#", "Explanation", "Record", "Team")));
    foreach ($this->REGATTA->getRanker()->rank($this->REGATTA) as $i => $rank) {
      $tab->addRow(array(new XTD(array(), array(new XTextInput('order[]', ($i + 1), array('size'=>2)),
                                                new XHiddenInput('team[]', $rank->team->id))),
			 new XTextInput('explanation[]', $rank->explanation),
			 new XA($this->link('rank', array('team' => $rank->team->id)), $rank->getRecord()),
			 new XTD(array('class'=>'drag'), $rank->team)),
                   array('class'=>'sortable'));
    }
    $f->add(new XSubmitP('set-ranks', "Set ranks"));
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Set records
    // ------------------------------------------------------------
    if (isset($args['set-records'])) {
      $team = DB::$V->reqTeam($args, 'team', $this->REGATTA, "Invalid team whose records to set.");
      $ids = DB::$V->reqList($args, 'race', null, "No list of races provided.");

      $affected = 0;
      foreach ($this->REGATTA->getRacesForTeam(Division::A(), $team) as $race) {
	if (count($this->REGATTA->getFinishes($race)) == 0)
	  continue;

	$ignore = (in_array($race->id, $ids)) ? null : 1;
	if ($ignore != $race->tr_ignore) {
	  $race->tr_ignore = $ignore;
	  DB::set($race);
	  $affected++;
	}
      }

      // @TODO: update request
      if ($affected == 0)
	Session::PA(new PA("No races affected.", PA::I));
      else
	Session::pa(new PA(sprintf("Updated %d races.", $affected)));
    }

    // ------------------------------------------------------------
    // Set ranks
    // ------------------------------------------------------------
    if (isset($args['set-ranks'])) {
      $teams = array();
      foreach ($this->REGATTA->getTeams() as $team)
        $teams[$team->id] = $team;
      $tids = DB::$V->reqList($args, 'team', count($teams), "Invalid list of teams provided.");
      $exps = DB::$V->reqList($args, 'explanation', count($teams), "Missing list of explanations.");
      $order = DB::$V->incList($args, 'order', count($tids));
      if (count($order) > 0)
        array_multisort($order, SORT_NUMERIC, $tids, $exps);

      $ranks = array();
      foreach ($tids as $i => $id) {
        if (!isset($teams[$id]))
          throw new SoterException("Invalid team provided.");
        $new_rank = ($i + 1);
        $new_expl = DB::$V->incString($exps, $i, 1, 101, null);

        if ($new_rank != $teams[$id]->dt_rank || $new_expl != $teams[$id]->dt_explanation) {
          $teams[$id]->dt_rank = $new_rank;
          $teams[$id]->dt_explanation = $new_expl;
          $ranks[] = $teams[$id];
        }
      }

      if (count($ranks) == 0) {
        Session::pa(new PA("No change in rankings.", PA::I));
        return;
      }

      // Set the rank and issue update request
      // @TODO: update request
      foreach ($ranks as $team)
        DB::set($team);
      Session::pa(new PA("Ranks saved."));
    }
  }
}
?>