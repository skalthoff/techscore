<?php
namespace scripts;

use \DB;
use \Season;
use \TSScriptException;

/**
 * Safely merge one conference into another while preserving school history.
 *
 * Example:
 * php bin/cli.php MergeConference --from nwicsa --to pccsc --season s26
 */
class MergeConference extends AbstractScript {

  public function __construct() {
    parent::__construct();
    $this->cli_opts = '--from <id> --to <id> [--season <url>] [--note <text>] [-n|--dry-run]';
    $this->cli_usage = " --from <id>      Source conference ID to merge from\n"
      . " --to <id>        Target conference ID to merge into\n"
      . " --season <url>   Season URL when reassignment becomes effective (default: current season)\n"
      . " --note <text>    Optional note recorded on source conference\n"
      . " -n, --dry-run    Print impact summary only";
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    if (self::getVerbosity() < 1) {
      self::setVerbosity(1);
    }

    $args = $this->parseArgs($opts);
    $source = strtoupper($args['from']);
    $target = strtoupper($args['to']);
    $dryRun = $args['dry-run'];

    if ($source === $target) {
      throw new TSScriptException('Source and target conference must be different.');
    }

    $sourceConference = DB::getConference($source);
    if ($sourceConference === null) {
      throw new TSScriptException(sprintf('Unknown source conference: %s', $source));
    }

    $targetConference = DB::getConference($target);
    if ($targetConference === null) {
      throw new TSScriptException(sprintf('Unknown target conference: %s', $target));
    }

    if ($this->isConferenceInactive($target)) {
      throw new TSScriptException(sprintf('Target conference %s is inactive.', $target));
    }

    $season = $this->resolveSeason($args['season']);
    $seasonId = (int)$season->id;

    $schoolsToMove = $this->scalarInt(
      'SELECT COUNT(*) FROM school WHERE conference = ?',
      's',
      array($source)
    );
    $accountsToMove = $this->scalarInt(
      'SELECT COUNT(*) FROM account_conference WHERE conference = ?',
      's',
      array($source)
    );

    self::errln(sprintf('Source conference: %s', $source));
    self::errln(sprintf('Target conference: %s', $target));
    self::errln(sprintf('Effective season: %s (#%d)', $season->url, $seasonId));
    self::errln(sprintf('Schools to move: %d', $schoolsToMove));
    self::errln(sprintf('Account conference links to move: %d', $accountsToMove));

    if ($dryRun) {
      self::errln('Dry run only, no changes written.');
      return;
    }

    DB::beginTransaction();
    try {
      $this->executePrepared(
        'UPDATE school_conference_history
         SET end_season = ?
         WHERE conference = ?
           AND end_season IS NULL
           AND (start_season IS NULL OR start_season < ?)',
        'isi',
        array($seasonId, $source, $seasonId)
      );

      $this->executePrepared(
        'INSERT INTO school_conference_history (school, conference, start_season, end_season, source)
         SELECT s.id, ?, ?, NULL, \"merge\"
         FROM school s
         WHERE s.conference = ?
           AND NOT EXISTS (
             SELECT 1
             FROM school_conference_history h
             WHERE h.school = s.id
               AND h.conference = ?
               AND h.start_season = ?
               AND h.end_season IS NULL
           )',
        'sissi',
        array($target, $seasonId, $source, $target, $seasonId)
      );

      $this->executePrepared(
        'UPDATE school SET conference = ? WHERE conference = ?',
        'ss',
        array($target, $source)
      );

      $this->executePrepared(
        'INSERT IGNORE INTO account_conference (account, conference)
         SELECT account, ?
         FROM account_conference
         WHERE conference = ?',
        'ss',
        array($target, $source)
      );

      $this->executePrepared(
        'DELETE FROM account_conference WHERE conference = ?',
        's',
        array($source)
      );

      $this->executePrepared(
        'UPDATE pub_update_conference SET conference = ? WHERE conference = ?',
        'ss',
        array($target, $source)
      );

      $this->executePrepared(
        'UPDATE conference
         SET inactive_on = NOW(),
             merged_into = ?,
             merged_on = NOW(),
             merge_note = ?
         WHERE id = ?',
        'sss',
        array($target, $args['note'], $source)
      );

      $this->executePrepared(
        'INSERT INTO conference_alias (alias_id, conference, active)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE conference = VALUES(conference), active = 1',
        'ss',
        array($source, $target)
      );

      DB::commit();
      DB::resetCache();
      self::errln('Merge completed successfully.');

    } catch (\Exception $e) {
      DB::rollback();
      throw $e;
    }
  }

  private function parseArgs(Array $opts) {
    $args = array(
      'from' => null,
      'to' => null,
      'season' => null,
      'note' => null,
      'dry-run' => false,
    );

    while (count($opts) > 0) {
      $opt = array_shift($opts);
      if ($opt == '-n' || $opt == '--dry-run') {
        $args['dry-run'] = true;
        continue;
      }
      if ($opt == '--from' || $opt == '--to' || $opt == '--season' || $opt == '--note') {
        if (count($opts) == 0) {
          throw new TSScriptException(sprintf('Missing argument for %s', $opt));
        }
        $key = ltrim($opt, '-');
        $args[$key] = trim(array_shift($opts));
        continue;
      }

      throw new TSScriptException(sprintf('Invalid option provided: %s', $opt));
    }

    if ($args['from'] === null || $args['to'] === null) {
      throw new TSScriptException('Both --from and --to are required.');
    }

    return $args;
  }

  private function isConferenceInactive($conferenceId) {
    $count = $this->scalarInt(
      'SELECT COUNT(*) FROM conference WHERE id = ? AND inactive_on IS NOT NULL',
      's',
      array($conferenceId)
    );
    return ($count > 0);
  }

  private function resolveSeason($seasonUrl = null) {
    if ($seasonUrl !== null && $seasonUrl !== '') {
      $season = DB::getSeason($seasonUrl);
      if ($season === null) {
        throw new TSScriptException(sprintf('Invalid season URL: %s', $seasonUrl));
      }
      return $season;
    }

    $season = Season::forDate(DB::T(DB::NOW));
    if ($season === null) {
      throw new TSScriptException('No current season found; provide --season <url>.');
    }
    return $season;
  }

  private function scalarInt($sql, $types, Array $params) {
    $stmt = $this->prepareStatement($sql, $types, $params);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int)$count;
  }

  private function executePrepared($sql, $types, Array $params) {
    $stmt = $this->prepareStatement($sql, $types, $params);
    $stmt->execute();
    if ($stmt->errno != 0) {
      $error = $stmt->error;
      $stmt->close();
      throw new TSScriptException(sprintf('SQL execution failed: %s', $error));
    }
    $stmt->close();
  }

  private function prepareStatement($sql, $types, Array $params) {
    $con = DB::connection();
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
      throw new TSScriptException(sprintf('Unable to prepare SQL statement: %s', $con->error));
    }

    if (count($params) > 0) {
      $bindParams = array($types);
      foreach ($params as $i => $value) {
        $bindParams[] = &$params[$i];
      }
      call_user_func_array(array($stmt, 'bind_param'), $bindParams);
    }

    return $stmt;
  }
}
