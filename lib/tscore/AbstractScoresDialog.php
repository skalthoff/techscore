<?php
/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractDialog.php');

/**
 * Parent class for all scores dialog. Sets up the menu with the
 * appropriate links.
 *
 * @author Dayan Paez
 * @version 2010-09-06
 */
abstract class AbstractScoresDialog extends AbstractDialog {
  public function __construct($title, FullRegatta $reg) {
    parent::__construct($title, $reg);

  }
  protected function setupPage() {
    parent::setupPage();

    // Add some menu
    $this->PAGE->addMenu(new XDiv(array('class'=>'menu'), array($ul = new XUl())));
    if ($this->REGATTA->scoring == Regatta::SCORING_TEAM) {
      $ul->add(new XLi(new XA(sprintf('/view/%d/scores',   $this->REGATTA->id), "All grids")));
      $ul->add(new XLi(new XA(sprintf('/view/%d/rotation', $this->REGATTA->id), "All races")));
      $ul->add(new XLi(new XA(sprintf('/view/%d/ranking',  $this->REGATTA->id), "Rankings")));
    }
    else {
      $ul->add(new XLi(new XA(sprintf('/view/%d/scores',     $this->REGATTA->id), "All scores")));
      $ul->add(new XLi(new XA(sprintf('/view/%d/div-scores', $this->REGATTA->id), "Summary")));
      if ($this->REGATTA->scoring == Regatta::SCORING_COMBINED)
        $ul->add(new XLi(new XA(sprintf('/view/%d/combined', $this->REGATTA->id), "All Divisions")));
      else
        $ul->add(new XLi(new XA(sprintf('/view/%d/chart', $this->REGATTA->id), "Rank history")));
      foreach ($this->REGATTA->getDivisions() as $div)
        $ul->add(new XLi(new XA(sprintf('/view/%d/scores/%s', $this->REGATTA->id, $div),
                                "$div Division")));
    }

    // Add meta tag
    $this->PAGE->head->add(new XMeta('timestamp', date('Y-m-d H:i:s')));
  }

  /**
   * Prepares the tiebreakers legend element (now a table) and returns it.
   *
   * @param Array $tiebreaker the associative array of symbol => explanation
   * @return XElem probably a table
   */
  protected function getLegend($tiebreakers) {
    $tab = new XQuickTable(array('class'=>'tiebreaker'), array("Sym.", "Explanation"));
    array_shift($tiebreakers);
    foreach ($tiebreakers as $exp => $ast)
      $tab->addRow(array($ast, $exp));
    return $tab;
  }

  /**
   * Helper method for team racing regattas
   *
   */
  protected function displayPlaces(Array $places = array()) {
    usort($places, 'Finish::compareEarned');
    $disp = "";
    $pens = array();
    foreach ($places as $i => $finish) {
      if ($i > 0)
	$disp .= "-";
      $modifiers = $finish->getModifiers();
      if (count($modifiers) > 0) {
	$disp .= $finish->earned;
        foreach ($modifiers as $modifier)
          $pens[] = $modifier->type;
      }
      else
	$disp .= $finish->score;
    }
    if (count($pens) > 0)
      $disp .= " " . implode(",", $pens);
    return $disp;
  }
}

?>