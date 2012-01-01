<?php
/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractDialog.php');

/**
 * Displays the rotation for a given regatta
 *
 */
class RotationDialog extends AbstractDialog {

  private $rotation;

  /**
   * Create a new rotation dialog for the given regatta
   *
   * @param Regatta $reg the regatta
   */
  public function __construct(Regatta $reg) {
    parent::__construct("Rotation", $reg);
    $this->rotation = $this->REGATTA->getRotation();
  }

  /**
   * Generates an HTML table for the given division
   *
   * @param Division $div the division
   * @return Rotation $rot
   */
  public function getTable(Division $div) {
    $tab = new Table(array(), array("class"=>"coordinate rotation"));
    $r = new Row(array(Cell::th(),
		       Cell::th()));
    $tab->addHeader($r);

    $races = $this->REGATTA->getRaces($div);
    foreach ($races as $race)
      $r->addCell(Cell::th($race));

    $row = 0;
    foreach ($this->REGATTA->getTeams() as $team) {
      $tab->addRow($r = new Row());
      $r->set("class", "row" . ($row++ % 2));
      $r->addCell(Cell::td(htmlentities($team->school->name)), Cell::th(htmlentities($team->name)));

      foreach ($races as $race) {
	$sail = $this->rotation->getSail($race, $team);
	$sail = ($sail !== false) ? $sail : "";
	$r->addCell(new Cell(htmlentities($sail)));
      }
    }

    return $tab;
  }

  /**
   * Creates a table for each division
   *
   */
  public function fillHTML(Array $args) {
    foreach ($this->REGATTA->getDivisions() as $div) {
      $this->PAGE->addContent($p = new XPort(sprintf("Division %s", $div)));
      $p->add($this->getTable($div));
    }
  }
}
