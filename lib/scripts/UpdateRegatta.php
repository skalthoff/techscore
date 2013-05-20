<?php
/*
 * This file is part of TechScore
 *
 * @package tscore/scripts
 */

require_once(dirname(__FILE__).'/../conf.php');
require_once('public/UpdateRequest.php');
require_once('public/ReportMaker.php');
require_once('xml5/TPublicPage.php');
require_once('AbstractScript.php');

/**
 * Update the given regatta, given as an argument.
 *
 * This update entails checking the regatta against the database and
 * updating either its rotation, its score or both. In addition, if
 * the regatta is not meant to be published, this will also attempt to
 * delete that regatta's folder and contents. In short, a full update.
 *
 * e.g.:
 * <code>
 * php UpdateScore 491 score
 * php UpdateScore 490 # which does both
 * </code>
 *
 * 2011-02-06: For the purpose of efficiency, granularize the regatta
 * update request to handle either detail changes, summary changes,
 * score changes, RP changes, or rotation changes, separately.
 * Depending on the change, UpdateRegatta will rewrite the correct
 * page(s) for that regatta.
 *
 * For example, a scores change would affect the different scores
 * pages and the front page but not the rotation page. A sumary change
 * affects only the front page, but no other pages. An RP change
 * affects the Divisional scores, or if single handed, the full scores
 * and the rotation. Finally, a settings change affects all pages.
 *
 * 2011-03-06: If a regatta has no finishes, do not generate score
 * pages, even if requested.
 *
 * 2012-11-07: For combined division, use the special ranker when (a)
 * performing syncs and (b) displaying
 *
 * @author Dayan Paez
 * @version 2010-08-27
 * @package scripts
 */
class UpdateRegatta extends AbstractScript {

  /**
   * Helper method will delete all the individual regatta files.
   *
   * $root should be the filesystem path to the root of the regatta's
   * directory. This method will then delete all the individual files
   * that could exist under that root.
   *
   * @param String $root the root to delete
   */
  public static function deleteRegattaFiles($root) {
    self::remove("$root/rotations/index.html");
    self::remove("$root/full-scores/index.html");
    self::remove("$root/A/index.html");
    self::remove("$root/B/index.html");
    self::remove("$root/C/index.html");
    self::remove("$root/D/index.html");
    self::remove("$root/index.html");
  }

  /**
   * Deletes the given regatta's information from the public site
   *
   * @param Regatta $reg the regatta whose information to delete.
   */
  public function runDelete(FullRegatta $reg) {
    $season = $reg->getSeason();
    if ((string)$season == "")
      return;

    // Regatta Nick Name can be empty, if the regatta has always been
    // personal, in which case there is nothing to delete, right?
    $nickname = $reg->nick;
    if (!empty($nickname))
      self::deleteRegattaFiles(sprintf('/%s/%s', $season, $nickname));
  }

  /**
   * Updates the regatta's pages according to the activity
   * requested. Will not create scores page if no finishes are registered!
   *
   * @param Regatta $reg the regatta to update
   * @param Array:UpdateRequest::Constant the activities to execute
   */
  public function run(FullRegatta $reg, Array $activities) {
    if ($reg->private || $reg->inactive !== null) {
      $this->runDelete($reg);
      return;
    }

    // Silently ignore team racing, as that's not ready
    if ($reg->scoring == Regatta::SCORING_TEAM) {
      $this->runTeamRacing($reg, $activities);
      return;
    }

    // In order to maintain all the regatta pages in sync, it is
    // necessary to first check what pages have been serialized
    $has_dir = false;
    $has_rotation = false;
    $has_fullscore = false;
    $has_combined = false;
    $has_divs = array((string)Division::A() => false,
                      (string)Division::B() => false,
                      (string)Division::C() => false,
                      (string)Division::D() => false);
    $dir = sprintf('%s/html%s', dirname(dirname(dirname(__FILE__))), $reg->getURL());
    if (is_dir($dir)) {
      $has_dir = true;
      if (is_dir($dir . '/rotations'))   $has_rotation = true;
      if (is_dir($dir . '/full-scores')) $has_fullscore = true;
      if (is_dir($dir . '/divisions'))   $has_combined = true;
      foreach ($has_divs as $div => $val) {
        if (is_dir($dir . '/' . $div))
          $has_divs[$div] = true;
      }
    }

    // Based on the list of activities, determine what files need to
    // be (re)serialized
    $sync = false;
    $sync_rp = false;

    $rotation = false;
    $divisions = false;
    $front = false;
    $full = false;
    $rot = $reg->getRotation();
    if (in_array(UpdateRequest::ACTIVITY_ROTATION, $activities)) {
      if ($rot->isAssigned()) {
        $rotation = true;

        // This check takes care of the fringe case when the rotation
        // is created AFTER there are already scores, etc.
        if (!$has_rotation) {
          $front = true;
          if ($reg->hasFinishes()) {
            $full = true;
            if (!$reg->isSingleHanded())
              $divisions = true;
          }
        }
      }
      elseif ($has_rotation) {
        // What if the rotation was removed?
        $season = $reg->getSeason();
        if ($season !== null && $reg->nick !== null)
          self::remove($reg->getURL() . '/rotations/index.html');

        $front = true;
        if ($reg->hasFinishes()) {
          $full = true;
          if (!$reg->isSingleHanded())
            $divisions = true;
        }
      }
    }
    if (in_array(UpdateRequest::ACTIVITY_SCORE, $activities)) {
      $sync = true;
      $sync_rp = true; // re-rank sailors
      $front = true;
      if ($reg->hasFinishes()) {
        $full = true;

        // Individual division scores (do not include if singlehanded as
        // this is redundant)
        if (!$reg->isSingleHanded())
          $divisions = true;

        // Check for the case when this is the first time a score is
        // entered, thus updating the rotation page as well
        if (!$has_fullscore && $rot->isAssigned())
          $rotation = true;
      }
      else {
        // It is possible that all finishes were removed, therefore,
        // delete all such directories, and regenerate rotation page
        $rotation = true;
        $season = $reg->getSeason();
        if ($season !== null && $reg->nick !== null) {
          $root = $reg->getURL();
          self::remove($root . 'full-scores/index.html');
          self::remove($root . 'A/index.html');
          self::remove($root . 'B/index.html');
          self::remove($root . 'C/index.html');
          self::remove($root . 'D/index.html');
          self::remove($root . 'divisions/index.html');
        }
      }
    }
    if (in_array(UpdateRequest::ACTIVITY_RP, $activities)) {
      $sync_rp = true;
      if ($reg->isSinglehanded()) {
        $rotation = true;
        if ($reg->hasFinishes()) {
          $front = true;
          $full = true;
        }
      }
      elseif ($reg->hasFinishes())
        $divisions = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_SUMMARY, $activities)) {
      $front = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_DETAILS, $activities) ||
        in_array(UpdateRequest::ACTIVITY_SEASON, $activities)) {
      // do them all (except RP)!
      $sync = true;
      $front = true;
      if ($rot->isAssigned())
        $rotation = true;
      if ($reg->hasFinishes()) {
        $full = true;

        // Individual division scores (do not include if singlehanded as
        // this is redundant)
        if (!$reg->isSingleHanded())
          $divisions = true;
      }
    }
    if (in_array(UpdateRequest::ACTIVITY_FINALIZED, $activities)) {
      $sync = true; // status change
      $sync_rp = true; // some races were removed
    }

    // ------------------------------------------------------------
    // For sanity sake, check for "display confusion": the possibility
    // that the current set of files reflects the expectation of a
    // standard-scoring regatta when in fact we are combined, or vice
    // versa. Any such evidence will result automatically in an update
    // of all the necessary resources
    // ------------------------------------------------------------
    if (($reg->scoring == Regatta::SCORING_STANDARD && $has_combined) ||
        ($reg->scoring == Regatta::SCORING_COMBINED && $has_divs['A'])) {
      $front = true;
      if ($reg->hasFinishes()) {
        $full = true;
        $divisions = true;
      }
      if ($rot->isAssigned())
        $rotation = true;
    }

    // ------------------------------------------------------------
    // Perform the updates
    // ------------------------------------------------------------
    if ($sync)       $reg->setData();
    if ($sync_rp)    $reg->setRpData();

    $D = $reg->getURL();
    $M = new ReportMaker($reg);

    if ($rotation)   $this->createRotation($D, $M);
    if ($front)      $this->createFront($D, $M);
    if ($full)       $this->createFull($D, $M);
    if ($divisions) {
      $root = $reg->getURL();
      if ($reg->scoring == Regatta::SCORING_STANDARD) {
        self::remove($root . 'divisions/index.html');
        foreach ($reg->getDivisions() as $div)
          $this->createDivision($D, $M, $div);
      }
      else {
        $this->createCombined($D, $M);
        self::remove($root . 'A/index.html');
        self::remove($root . 'B/index.html');
        self::remove($root . 'C/index.html');
        self::remove($root . 'D/index.html');
      }
    }
  }

  /**
   * Interpreter for team racing regattas
   *
   * No need to check if public, since parent performs check ahead of
   * time.
   */
  private function runTeamRacing(FullRegatta $reg, Array $activities) {
    $has_dir = false;
    // "Rotation" tab always exists in team racing
    $has_rotation = true;
    $has_fullscore = false;
    $dir = sprintf('%s/html%s', dirname(dirname(dirname(__FILE__))), $reg->getURL());
    if (is_dir($dir)) {
      $has_dir = true;
      if (is_dir($dir . '/rotations'))   $has_rotation = true;
      if (is_dir($dir . '/full-scores')) $has_fullscore = true;
    }

    // Based on the list of activities, determine what files need to
    // be (re)serialized
    $sync = false;
    $sync_rp = false;

    $rotation = false;
    $allraces = false;
    $front = false;
    $full = false;
    $rot = $reg->getRotation();
    if (in_array(UpdateRequest::ACTIVITY_ROTATION, $activities)) {
      $sync = true;
      $rotation = true;
      $allraces = true;

      // This check takes care of the fringe case when the rotation
      // is created AFTER there are already scores, etc.
      if (!$has_rotation) {
        $front = true;
        if ($reg->hasFinishes()) {
          $full = true;
        }
      }
    }
    if (in_array(UpdateRequest::ACTIVITY_SCORE, $activities)) {
      $sync = true;
      $sync_rp = true; // re-rank sailors
      $front = true;
      $allraces = true;
      if ($reg->hasFinishes()) {
        $full = true;
      }
      else {
        // It is possible that all finishes were removed, therefore,
        // delete all such directories, and regenerate rotation page
        $season = $reg->getSeason();
        if ($season !== null && $reg->nick !== null) {
          $root = $reg->getURL();
          self::remove($root . 'full-scores/index.html');
        }
      }
    }
    if (in_array(UpdateRequest::ACTIVITY_RP, $activities)) {
      $sync_rp = true;
      $front = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_SUMMARY, $activities)) {
      $front = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_DETAILS, $activities) ||
        in_array(UpdateRequest::ACTIVITY_SEASON, $activities)) {
      // do them all (except RP)!
      $sync = true;
      $front = true;
      $rotation = true;
      $allraces = true;
      if ($reg->hasFinishes()) {
        $full = true;
      }
    }
    if (in_array(UpdateRequest::ACTIVITY_FINALIZED, $activities)) {
      $sync = true; // status change
      $sync_rp = true; // some races were removed
      $front = true;
      $full = true;
    }
    if (in_array(UpdateRequest::ACTIVITY_RANK, $activities)) {
      $sync = true;
      $sync_rp = true;
      $front = true;
      $full = true;
    }

    // ------------------------------------------------------------
    // Perform the udpates
    // ------------------------------------------------------------
    if ($sync)       $reg->setData();
    if ($sync_rp)    $reg->setRpData();

    $D = $reg->getURL();
    $M = new ReportMaker($reg);

    if ($rotation)   $this->createRotation($D, $M);
    if ($front)      $this->createFront($D, $M);
    if ($full)       $this->createFull($D, $M);
    if ($allraces)   $this->createAllRaces($D, $M);
  }

  // ------------------------------------------------------------
  // Helper methods for standardized file creation
  // ------------------------------------------------------------

  /**
   * Creates and writes the front page (index) in the given directory
   *
   * @param String $dirname the directory
   * @param ReportMaker $maker the maker
   * @throws RuntimeException when writing is no good
   */
  private function createFront($dirname, ReportMaker $maker) {
    $filename = $dirname . 'index.html';
    $page = $maker->getScoresPage();
    self::writeXml($filename, $page);
  }

  /**
   * Creates and writes the full scores page in the given directory
   *
   * @param String $dirname the directory
   * @param ReportMaker $maker the maker
   * @throws RuntimeException when writing is no good
   * @see createFront
   */
  private function createFull($dirname, ReportMaker $maker) {
    $page = $maker->getFullPage();
    $path = $dirname . 'full-scores/index.html';
    self::writeXml($path, $page);
  }

  /**
   * Creates and writes the division summary page in the given directory
   *
   * @param String $dirname the directory
   * @param ReportMaker $maker the maker
   * @param Division $div the division to write about
   * @throws RuntimeException when writing is no good
   * @see createFront
   */
  private function createDivision($dirname, ReportMaker $maker, Division $div) {
    $path = $dirname . $div . '/index.html';
    $page = $maker->getDivisionPage($div);
    self::writeXml($path, $page);
  }

  /**
   * Creates and writes the combined division page in the given directory
   *
   * @param String $dirname the directory
   * @param ReportMaker $maker the maker
   * @throws RuntimeException when writing is no good
   * @see createFront
   */
  private function createCombined($dirname, ReportMaker $maker) {
    $path = $dirname . 'divisions/index.html';
    $page = $maker->getCombinedPage();
    self::writeXml($path, $page);
  }

  /**
   * Creates and writes the rotation page in the given directory
   *
   * @param String $dirname the directory
   * @param ReportMaker $maker the maker
   * @throws RuntimeException when writing is no good
   * @see createFront
   */
  private function createRotation($dirname, ReportMaker $maker) {
    $path = $dirname . 'rotations/index.html';
    $page = $maker->getRotationPage();
    self::writeXml($path, $page);
  }

  /**
   * Creates and writes the all-races page for team racing
   *
   * @param String $dirname the directory
   * @param ReportMaker $maker the maker
   * @throws RuntimeException when writing is no good
   * @see createFront
   */
  private function createAllRaces($dirname, ReportMaker $maker) {
    $path = $dirname . 'all/index.html';
    $page = $maker->getAllRacesPage();
    self::writeXml($path, $page);
  }

  // ------------------------------------------------------------
  // CLI
  // ------------------------------------------------------------
  public function __construct() {
    parent::__construct();
    $this->cli_opts = '<regatta-id> <activity>';
    $this->cli_usage = "Activity must be one of:\n";
    foreach (UpdateRequest::getTypes() as $type)
      $this->cli_usage .= "\n - $type";
  }
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  // Validate arguments
  $P = new UpdateRegatta();
  $opts = $P->getOpts($argv);
  if (count($opts) < 2)
    throw new TSScriptException("Missing argument(s)");
  if (($reg = DB::getRegatta(array_shift($opts))) === null)
    throw new TSScriptException("Invalid regatta");

  $pos_actions = UpdateRequest::getTypes();
  $actions = array();
  foreach ($opts as $opt) {
    if (!isset($pos_actions[$opt]))
      throw new TSScriptException("Invalid activity $opt");
    $actions[] = $opt;
  }
  $P->run($reg, $actions);
}
?>
