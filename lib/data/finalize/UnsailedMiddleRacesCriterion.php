<?php
namespace data\finalize;

use \DB;
use \Division;
use \Regatta;

/**
 * Enforces that all "middle" races are sailed.
 *
 * @author Dayan Paez
 * @version 2015-06-20
 */
class UnsailedMiddleRacesCriterion extends FinalizeCriterion {

  public function canApplyTo(Regatta $regatta) {
    // return ($regatta->scoring != Regatta::SCORING_TEAM);
    return true;
  }

  public function getFinalizeStatuses(Regatta $regatta) {
    $list = $this->getUnsailedMiddleRaces($regatta);
    $mess = "No middle races unscored.";
    $type = FinalizeStatus::VALID;
    if (count($list) > 0) {
      $nums = array();
      foreach ($list as $race)
        $nums[] = $race->number;
      $mess = "The following races must be scored: " . DB::makeRange($nums);
      $type = FinalizeStatus::ERROR;
      $can_finalize = false;
    }
    return array(new FinalizeStatus($type, $mess));
  }

  /**
   * Fetch list of unsailed races
   *
   */
  private function getUnsailedMiddleRaces(Regatta $regatta) {
    $divs = ($regatta->scoring == Regatta::SCORING_STANDARD) ?
      $regatta->getDivisions() :
      array(Division::A());
    
    $list = array();
    foreach ($divs as $division) {
      $prevNumber = 0;
      foreach ($regatta->getScoredRaces($division) as $race) {
        for ($i = $prevNumber + 1; $i < $race->number; $i++)
          $list[] = $regatta->getRace($division, $i);
        $prevNumber = $race->number;
      }
    }
    return $list;
  }
}