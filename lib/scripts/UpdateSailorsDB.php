<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package scripts
 */

/**
 * Pulls information from the ICSA database and updates the local
 * sailor database with the data.
 *
 * 2011-09-12: Sets active flag to true
 *
 * @author Dayan Paez
 * @version 2010-03-02
 */
class UpdateSailorsDB {

  /**
   * The URL to check for new sailors
   */
  public $SAILOR_URL = 'http://www.collegesailing.org/directory/individual/sailorapi.asp';

  /**
   * The URL to check for new coaches
   */
  public $COACH_URL = 'http://www.collegesailing.org/directory/individual/coachapi.asp';
  
  /**
   * Errors encountered
   */
  private $errors;

  /**
   * Warnings issued
   */
  private $warnings;

  /**
   * @var MySQLi the connection
   */
  private $con;

  private $verbose = false;

  /**
   * Creates a new UpdateSailorsDB object
   *
   */
  public function __construct($verbose = false) {
    $this->errors = array();
    $this->warnings = array();
    $this->con = DB::connection();
    $this->verbose = $verbose;
  }

  /**
   * Fetch the errors
   *
   * @return Array<String> error messages
   */
  public function errors() { return $this->errors; }

  /**
   * Fetch the warnings
   *
   * @return Array<String> warning messages
   */
  public function warnings() { return $this->warnings; }

  /**
   * Updates the information in the database about the given sailor
   *
   * @param Sailor $sailor the sailor
   */
  private function updateSailor(Sailor $sailor) {
    $sailor->role = ($sailor instanceof Coach) ? Sailor::COACH : Sailor::STUDENT;
    $sailor->active = 1;
    $cur = DB::getICSASailor($sailor->icsa_id);

    $update = false;
    if ($cur !== null) {
      $sailor->id = $cur->id;
      $update = true;
    }
    DB::set($sailor, $update);
  }

  /**
   * If verbose output enabled, prints the given message
   *
   * @param String $mes the message to output, appending a new line,
   * prepending a timestamp
   */
  private function log($mes) {
    if ($this->verbose !== false)
      printf("%s\t%s\n", date('Y-m-d H:i:s'), $mes);
  }

  /**
   * Runs the update
   *
   */
  public function update() {
    $this->log("Starting: fetching and parsing sailors " . $this->SAILOR_URL);
    $schools = array();
    
    if (($xml = @simplexml_load_file($this->SAILOR_URL)) === false) {
      $this->errors[] = "Unable to load XML from " . $this->SAILOR_URL;
    }
    else {
      $this->log("Inactivating sailors");
      // resets all sailors to be inactive
      RpManager::inactivateRole(Sailor::STUDENT);
      $this->log("Sailors deactivated");
      // parse data
      foreach ($xml->sailor as $sailor) {
	try {
	  $s = new Sailor();
	  $s->icsa_id = (int)$sailor->id;

	  // keep cache of schools
	  $school_id = trim((string)$sailor->school);
	  if (!isset($schools[$school_id])) {
	    $schools[$school_id] = DB::getSchool($school_id);
	    $this->log(sprintf("Fetched school (%s) %s", $school_id, $schools[$school_id]));
	  }

	  if ($schools[$school_id] !== null) {
	    $s->school = $schools[$school_id];
	    $s->last_name  = $this->con->real_escape_string($sailor->last_name);
	    $s->first_name = $this->con->real_escape_string($sailor->first_name);
	    $s->year = (int)$sailor->year;
	    $s->gender = $sailor->gender;

	    $this->updateSailor($s);
	    $this->log("Activated sailor $s");
	  }
	  else
	    $this->warnings[$school_id] = "Missing school $school_id.";
	} catch (Exception $e) {
	  $this->warnings[] = "Invalid sailor information: " . print_r($sailor, true);
	}
      }
    }

    // Coaches
    $this->log("Starting: fetching and parsing coaches " . $this->COACH_URL);
    if (($xml = @simplexml_load_file($this->COACH_URL)) === false) {
      $this->errors[] = "Unable to load XML from " . $this->COACH_URL;
    }
    else {
      $this->log("Inactivating coaches");
      RpManager::inactivateRole(Sailor::COACH);
      $this->log("Coaches inactivated");
      // parse data
      foreach ($xml->coach as $sailor) {
	try {
	  $s = new Coach();
	  $s->icsa_id = (int)$sailor->id;

	  // keep cache of schools
	  $school_id = trim((string)$sailor->school);
	  if (!isset($schools[$school_id])) {
	    $schools[$school_id] = DB::getSchool($school_id);
	    $this->log(sprintf("Fetched school (%s) %s", $school_id, $schools[$school_id]));
	  }

	  if ($schools[$school_id] !== null) {
	    $s->school = $schools[$school_id];
	    $s->last_name  = $this->con->real_escape_string($sailor->last_name);
	    $s->first_name = $this->con->real_escape_string($sailor->first_name);
	    $s->year = (int)$sailor->year;
	    $s->gender = $sailor->gender;

	    $this->updateSailor($s);
	    $this->log("Activated coach $s");
	  }
	  else
	    $this->warnings[$school_id] = "Missing school $school_id.";
	} catch (Exception $e) {
	  $warnings[] = "Invalid coach information: " . print_r($sailor, true);
	}
      }
    }
  }
}

if (isset($argv) && basename(__FILE__) == basename($argv[0])) {
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  $opt = getopt('v');
  $db = new UpdateSailorsDB(isset($opt['v']));
  $db->update();
  $err = $db->errors();
  if (count($err) > 0) {
    echo "----------Error(s)\n";
    foreach ($err as $mes)
      printf("  %s\n", $mes);
  }
  $err = $db->warnings();
  if (count($err) > 0) {
    echo "----------Warning(s)\n";
    foreach ($err as $mes)
      printf("  %s\n", $mes);
  }
}
?>
