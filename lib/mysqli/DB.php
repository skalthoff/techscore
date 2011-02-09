<?php
/**
 * A different way of serializing and deserializing objects using the
 * DBM class created by Dayan Paez.
 *
 * @author Dayan Paez
 * @version 2011-01-22
 * @package mysql
 */

require_once('DBC.php');
require_once('MySQLi_delegate.php');
require_once('MySQLi_Query.php');

/**
 * Provides some more functionality
 *
 */
class DBME extends DBM {
  /**
   * Empty objects to serve as prototypes
   */
  public static $CONFERENCE = null;
  public static $REGATTA = null;
  public static $SCHOOL = null;
  public static $SAILOR = null;
  public static $SCORE = null;
  public static $VENUE = null;
  public static $TEAM = null;
  public static $RACE = null;
  public static $NOW = null;
  public static $RP = null;
  
  // use this method to initialize the different objects as well
  public static function setConnection(MySQLi $con) {
    self::$CONFERENCE = new Dt_Conference();
    self::$REGATTA = new Dt_Regatta();
    self::$SCHOOL = new Dt_School();
    self::$SAILOR = new Dt_Sailor();
    self::$SCORE = new Dt_Score();
    self::$VENUE = new Dt_Venue();
    self::$TEAM = new Dt_Team();
    self::$RACE = new Dt_Race();
    self::$NOW = new DateTime();
    self::$RP = new Dt_Rp();

    DBM::setConnection($con);
  }
}

class Dt_Regatta extends DBObject {
  public $name;
  public $nick;
  protected $start_time;
  protected $end_date;
  protected $venue;
  public $type;
  protected $finalized;
  public $scoring;
  public $num_divisions;
  public $num_races;
  public $hosts;
  public $confs;
  public $boats;
  public $singlehanded;
  public $season;
  public $status;

  public function db_type($field) {
    switch ($field) {
    case 'start_time':
    case 'end_date':
    case 'finalized':
      return DBME::$NOW;

    case 'venue':
      return DBME::$VENUE;

    default:
      return parent::db_type($field);
    }
  }
  public function db_cache() { return true; }
  public function db_order() { return 'start_time'; }
  public function db_order_by() { return false; }

  /**
   * Deletes all data about my teams
   */
  public function deleteTeams() {
    $q = DBME::createQuery(MySQLi_Query::DELETE);
    $q->fields(array(), DBME::$TEAM->db_name());
    $q->where(new MyCond('regatta', $this->id));
    DBME::query($q);
  }

  public function getTeams() {
    return DBME::getAll(DBME::$TEAM, new MyCond('regatta', $this->id));
  }

  public function getHosts() {
    $list = array();
    foreach (explode(',', $this->hosts) as $id) {
      $sch = DBME::get(DBME::$SCHOOL, $id);
      if ($sch !== null)
        $list[] = $sch;
    }
    return $list;
  }
}

class Dt_Venue extends DBObject {
  public $name;
  public $address;
  public $city;
  public $state;
  public $zipcode;

  public function db_name() { return 'venue'; }
}

class Dt_Team extends DBObject {
  protected $regatta;
  protected $school;
  public $name;
  public $rank;
  public $rank_explanation;

  public function db_type($field) {
    switch ($field) {
    case 'regatta':
      return DBME::$REGATTA;
    case 'school':
      return DBME::$SCHOOL;
    default:
      return parent::db_type($field);
    }
  }
  public function db_order() { return 'rank'; }

  public function __toString() {
    return sprintf("%s %s", $this->__get('school')->nick_name, $this->name);
  }
}

class Dt_Race extends DBObject {
  protected $regatta;
  public $division;
  public $number;

  public function db_name() { return 'race'; }
  public function db_type($field) {
    if ($field == 'regatta') return DBME::$REGATTA;
    return parent::db_type($field);
  }
}

class Dt_School extends DBObject {
  public $name;
  public $nick_name;
  public $conference;
  public $city;
  public $state;

  public function db_name() { return 'school'; }
  public function db_cache() { return true; }

  public function __toString() {
    return $this->name;
  }
}

class Dt_Conference extends DBObject {
  public $name;
  public function db_name() { return 'conference'; }
  public function __toString() { return $this->id; }
}

class Dt_Score extends DBObject {
  protected $team;
  protected $race;

  public $penalty;
  public $score;
  public $explanation;

  public function db_type($field) {
    if ($field == 'team')
      return DBME::$TEAM;
    if ($field == 'race')
      return DBME::$RACE;
    return parent::db_type($field);
  }
  public function db_name() { return 'score'; }
}

class Dt_Rp extends DBObject {
  protected $team;
  protected $race;
  protected $sailor;
  public $boat_role;

  public function db_type($field) {
    if ($field == 'team')   return DBME::$TEAM;
    if ($field == 'race')   return DBME::$RACE;
    if ($field == 'sailor') return DBME::$SAILOR;
    return parent::db_type($field);
  }
  public function db_name() { return 'rp'; }
}

class Dt_Sailor extends DBObject {
  public $icsa_id;
  protected $school;
  public $last_name;
  public $first_name;
  public $year;
  public $role;

  public function db_name() { return 'sailor'; }
  public function db_type($field) {
    if ($field == 'school')
      return DBME::$SCHOOL;
    return parent::db_type($field);
  }
  public function __toString() {
    $suffix = ($this->icsa_id === null) ? ' *' : '';
    return sprintf('%s %s \'%s%s',
		   $this->first_name,
		   $this->last_name,
		   substr($this->year, 2),
		   $suffix);
  }
}
?>
