<?php
/**
 * This file is part of TechScore
 *
 * @package tscore
 */
require_once('conf.php');

/**
 * Displays the scores table for a given regatta's division
 *
 * @author Dayan Paez
 * @created 2010-02-01
 */
class ScoresDivisionDialog extends AbstractScoresDialog {

  private $division;

  /**
   * Create a new rotation dialog for the given regatta
   *
   * @param Regatta $reg the regatta
   * @param Division $div the division
   * @throws InvalidArgumentException if the division is not in the
   * regatta
   */
  public function __construct(Regatta $reg, Division $div) {
    parent::__construct("Race results", $reg);
    if (!in_array($div, $reg->getDivisions())) {
      throw new InvalidArgumentException("No such division ($div) in this regatta.");
    }
    $this->division = $div;
  }

  /**
   * Create and display the score table
   *
   */
  public function fillHTML(Array $args) {
    $division  = $this->division;

    $this->PAGE->addContent($p = new Port("Division $division results"));
    $elems = $this->getTable();
    $p->addChild(array_shift($elems));
    if (count($elems) > 0) {
      $p->addChild(new Heading("Tiebreaker legend"));
      $p->addChild($elems[0]);
    }
  }

  /**
   * Fetches just the table of results
   *
   * @param String $PREFIX the prefix to add to image resource URLs
   * @param String $link_schools if not null, the prefix to add to a
   * link from the school's name using the school's ID
   *
   * @return Array the table element
   */
  public function getTable($PREFIX = "", $link_schools = null) {
    $rpManager = $this->REGATTA->getRpManager();
    $division = $this->division;

    $ELEM = array($tab = new Table());
    $tab->addAttr("class", "results");
    $tab->addAttr("class", "coordinate");
    $tab->addAttr("class", "narrow");

    $tab->addHeader(new Row(array(Cell::th(), // superscript
				  Cell::th(), // rank
				  Cell::th(),
				  Cell::th("Team"),
				  $penalty_th = Cell::th(""),
				  Cell::th("Total"),
				  Cell::th("Sailors"),
				  Cell::th(""))));
    $has_penalties = false;

    // print each ranked team
    //  - keep track of different ranks and tiebrakers
    $tiebreakers = array("" => "");
    $ranks = $this->REGATTA->scorer->rank($this->REGATTA, $division);
    foreach ($ranks as $rank) {
      if (!empty($rank->explanation) && !isset($tiebreakers[$rank->explanation])) {
	$count = count($tiebreakers);
	switch ($count) {
	case 1:
	  $tiebreakers[$rank->explanation] = "*";
	  break;
	case 2:
	  $tiebreakers[$rank->explanation] = "**";
	  break;
	default:
	  $tiebreakers[$rank->explanation] = chr(95 + $count);
	}
      }
    }
    
    $rowIndex = 0;
    $order = 1;
    foreach ($ranks as $rank) {

      // get total score for this team
      $total = 0;
      $races = $this->REGATTA->getScoredRaces($division);
      foreach ($races as $race) {
	$finish = $this->REGATTA->getFinish($race, $rank->team);
	$total += $finish->score;
      }
      $penalty = $this->REGATTA->getTeamPenalty($rank->team, $division);
      if ($penalty != null) {
	$total += 20;
      }

      $ln = $rank->team->school->name;
      if ($link_schools !== null)
	$ln = new Link(sprintf('%s/%s', $link_schools, $rank->team->school->id), $ln);

      // deal with explanations
      $sym = sprintf("<sup>%s</sup>", $tiebreakers[$rank->explanation]);

      // fill the two header rows up until the sailor names column
      $img = ($rank->team->school->burgee == null) ? '' :
	new Image(sprintf('%s/img/schools/%s.png', $PREFIX, $rank->team->school->id),
		  array('alt' => $rank->team->school->id, 'height'=>'30px'));
      $r1 = new Row(array(new Cell($sym, array('title'=>$rank->explanation, 'class'=>'tiebreaker')),
			  $ord = new Cell($order++),
			  new Cell($img),
			  $sch = new Cell($ln),
			  $pen = new Cell(),
			  new Cell($total)));
      $r1->addAttr("class", "row" . $rowIndex % 2);
      $sch->addAttr("class", "strong");
      $sch->addAttr("align", "left");
      if ($penalty != null) {
	$com = sprintf("%s (+20 points)", $penalty->comments);
	$pen->addChild(new Text($penalty->type));
	$pen->addAttr("title", $com);
      }

      $r2 = new Row(array(new Cell(), new Cell(), new Cell(),
			  $sch = new Cell($rank->team->name),
			  new Cell(), new Cell()));
      $r2->addAttr("class", "row" . $rowIndex % 2);
      $sch->addAttr("align", "left");

      $headerRows = array($r1, $r2);

      // ------------------------------------------------------------
      // Skippers and crews
      foreach (array(RP::SKIPPER, RP::CREW) as $index => $role) {
	$sailors  = $rpManager->getRP($rank->team, $division, $role);
	    
	$is_first = true;
	$s_rows = array();
	if (count($sailors) == 0)
	  $headerRows[$index]->addCell(new Cell(), new Cell());
	foreach ($sailors as $s) {
	  if ($is_first) {
	    $row = $headerRows[$index];
	    $is_first = false;
	  }
	  else {
	    $row = new Row(array(new Cell(),
				 new Cell(),
				 new Cell(),
				 new Cell(),
				 new Cell(),
				 new Cell()));
	    $row->addAttr("class", "row" . $rowIndex % 2);
	    $s_rows[] = $row;
	  }

	  if (count($s->races_nums) == count($races))
	    $amt = "";
	  else
	    $amt = Utilities::makeRange($s->races_nums);
	  $row->addCell($s_cell = new Cell($s->sailor),
			$r_cell = new Cell($amt));
	  $s_cell->addAttr("align", "right");
	  $r_cell->addAttr("align", "left");
	  $r_cell->addAttr("class", "races");
	}

	// Add rows
	$tab->addRow($headerRows[$index]);
	foreach ($s_rows as $r)
	  $tab->addRow($r);
      }
      $rowIndex++;
    } // end of table

    // Print tiebreakers $table
    if (count($tiebreakers) > 1)
      $ELEMS[] = $this->getLegend($tiebreakers);
    return $ELEM;
  }
}
