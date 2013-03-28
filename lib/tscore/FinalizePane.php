<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package tscore
 */

require_once('tscore/AbstractPane.php');

/**
 * Mighty useful pane for reviewing and finalizing regatta
 *
 * @author Dayan Paez
 * @created 2013-03-28
 */
class FinalizePane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Settings", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Review and finalize"));
    $p->add(new XP(array(), "Please review the information about this regatta that appears below, and address any outstanding issues."));

    $can_finalize = true;
    $p->add($tab = new XQuickTable(array('id'=>'finalize-issues'), array("Status", "Issue")));

    $VALID = new XImg(WS::link('/inc/img/s.png'), "✓");
    $ERROR = new XImg(WS::link('/inc/img/e.png'), "X");
    $WARN  = new XImg(WS::link('/inc/img/i.png'), "⚠");

    
    $list = $this->getUnsailedMiddleRaces();
    $mess = "No middle races unscored.";
    $icon = $VALID;
    if (count($list) > 0) {
      $mess = "The following races must be scored: " . implode(", ", $list);
      $icon = $ERROR;
      $can_finalize = false;
    }
    $tab->addRow(array($icon, $mess));



    if ($can_finalize) {
      $p->add($f = $this->createForm());
      $f->add(new FItem($chk = new XCheckboxInput('approve', 1, array('id'=>'approve')),
                        new XLabel('approve',
                                   "I wish to finalize this regatta.",
                                   array("class"=>"strong"))));
      $f->add(new XSubmitP("finalize", "Finalize!"));
    }
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Finalize
    if (isset($args['finalize'])) {
      if (!$this->REGATTA->hasFinishes())
        throw new SoterException("You cannot finalize a project with no finishes. To delete the regatta, please mark it as \"personal\".");

      $list = $this->getUnsailedMiddleRaces();
      if (count($list) > 0)
        throw new SoterException("Cannot finalize with unsailed races: " . implode(", ", $list));

      if (!isset($args['approve']))
        throw new SoterException("Please check the box to finalize.");

      $this->REGATTA->finalized = new DateTime();
      $removed = 0;
      foreach ($this->REGATTA->getUnscoredRaces() as $race) {
        $this->REGATTA->removeRace($race);
        $removed++;
      }
      DB::set($this->REGATTA);
      Session::pa(new PA("Regatta has been finalized."));
      if ($removed > 0)
        Session::pa(new PA("Removed $removed unsailed races.", PA::I));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_FINALIZED);
    }
  }

  /**
   * Fetch list of unsailed races
   *
   */
  private function getUnsailedMiddleRaces() {
    $divs = ($this->REGATTA->scoring == Regatta::SCORING_STANDARD) ?
      $this->REGATTA->getDivisions() :
      array(Division::A());
    
    $list = array();
    foreach ($divs as $division) {
      $prevNumber = 0;
      foreach ($this->REGATTA->getScoredRaces($division) as $race) {
        for ($i = $prevNumber + 1; $i < $race->number; $i++)
          $list[] = $this->REGATTA->getRace($division, $i);
        $prevNumber = $race->number;
      }
    }
    return $list;
  }
}
?>