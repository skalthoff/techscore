<?php
/**
 * This class is part of TechScore
 *
 * @version 2.0
 * @author Dayan Paez
 * @package regatta
 */
require_once('conf.php');

/**
 * Class for regatta objects. Each object is responsible for
 * communicating with the database and retrieving all sorts of
 * pertinent informations.
 *
 * 2010-02-16: Created TempRegatta which extends this class.
 *
 * 2010-03-07: Provided for combined divisions
 *
 * @author Dayan Paez
 * @created 2009-10-01
 */
class Regatta implements RaceListener, FinishListener {

  /**
   * To allow for multiple regatta databases, the following maps the
   * database name to the prefix
   */
  protected $DB = SQL_DB;

  private $id;
  private $con; // MySQL connection object
  private $scorer;

  // Keys for data
  const NAME       = "name";
  const START_TIME = "start_time";
  const END_DATE   = "end_date";
  const DURATION   = "duration";
  const FINALIZED  = "finalized";
  const TYPE       = "type";
  const VENUE      = "venue";
  const SCORING    = "scoring";

  /**
   * Standard scoring
   */
  const SCORING_STANDARD = "standard";

  /**
   * Combined scoring
   */   
  const SCORING_COMBINED = "combined";

  // Properties
  private $properties = null;

  // Managers
  private $rotation;
  private $rp;

  /**
   * Sends the query to the database and handles errors. Returns the
   * resultant mysqli_result object
   */
  public function query($string) {
    if ($q = $this->con->query($string)) {
      return $q;
    }
    throw new BadFunctionCallException($q->error . ": " . $string);
  }

  /**
   * Creates a new regatta object using the specified connection
   *
   * @param int $id the id of the regatta
   *
   * @throws InvalidArgumentException if not a valid regatta ID
   */
  public function __construct($id) {
    if (!is_numeric($id))
      throw new InvalidArgumentException(sprintf("Illegal regatta id value (%s).", $id));

    $this->id  = (int)$id;
    $this->con = new MySQLi(SQL_HOST, SQL_USER, SQL_PASS, $this->DB);
    $this->scorer = new ICSAScorer();

    // Update the properties
    $q = sprintf('select regatta.id, regatta.name, ' .
		 'regatta.start_time, regatta.end_date, regatta.venue, ' .
		 'regatta.type, regatta.finalized, regatta.scoring ' .
		 'from regatta ' .
		 'where regatta.id = "%s"',
		 $this->id);
    $result = $this->query($q);
    if ($result->num_rows > 0) {
      $this->properties = $result->fetch_assoc();

      $start = new DateTime($this->properties[Regatta::START_TIME]);
      $end   = new DateTime($this->properties[Regatta::END_DATE]);
      date_time_set($end, 0, 0, 0);

      $this->properties[Regatta::START_TIME] = $start;
      $this->properties[Regatta::END_DATE]   = $end;

      // Calculate duration
      $duration = 1 + (date_format($end, "U") -
		   date_format($this->getDay($start), "U")) /
	(3600 * 24);

      $this->properties[Regatta::DURATION] = $duration;

      // Venue
      $this->properties[Regatta::VENUE] =
	Preferences::getVenue($this->properties[Regatta::VENUE]);
    }
    else {
      $m = "Invalid ID for regatta: " . $this->con->error;
      throw new InvalidArgumentException($m);
    }

    // Managers
    $this->rotation = new Rotation($this);
    $this->rp       = new RpManager($this);
  }

  /**
   * Returns the specified property.
   *
   * @param Regatta::Const $property one of the class constants
   * @return the specified property
   * @throws InvalidArgumentException if the property is invalid.
   */
  public function get($property) {
    if (!array_key_exists($property, $this->properties)) {
      $m = "Property $property not supported in regattas.";
      throw new InvalidArgumentException($m);
    }
    return $this->properties[$property];
  }

  public function __get($name) {
    if ($name == "scorer") {
      return $this->scorer;
    }
    throw new InvalidArgumentException("No such Regatta property $name.");
  }

  /**
   * Commits the specified property
   *
   * @param Regatta::Const $property one of the class constants
   * @param object $value value whose string representation should be
   * used for the given property
   *
   * @throws InvalidArgumentException if the property is invalid.
   */
  public function set($property, $value) {
    if (!array_key_exists($property, $this->properties)) {
      $m = "Property $property not supported in regattas.";
      throw new InvalidArgumentException($m);
    }
    if ($value == null)
      $strvalue = 'NULL';
    elseif (in_array($property, array(Regatta::START_TIME, Regatta::END_DATE))) {
      if (!($value instanceof DateTime)) {
	$m = sprintf("Property %s must be a valid DateTime object.", $property);
	throw new InvalidArgumentException($m);
      }
      $strvalue = sprintf('"%s"', $value->format("Y-m-d H:i:s"));
    }
    else
      $strvalue = sprintf('"%s"', $value);

    $this->properties[$property] = $value;
    $q = sprintf('update regatta set %s = %s where id = "%s"',
		 $property, $strvalue, $this->id);
    $this->query($q);
  }

  //
  // Daily summaries
  //

  /**
   * Gets the daily summary for the given day
   *
   * @param DateTime $day the day summary to return
   * @return String the summary
   */
  public function getSummary(DateTime $day) {
    $q = sprintf('select summary from daily_summary ' .
		 'where regatta = "%s" and summary_date = "%s"',
		 $this->id, $day->format("Y-m-d"));
    $res = $this->query($q);
    if ($res->num_rows == 0)
      return '';
    return stripslashes($res->fetch_object()->summary);
  }

  /**
   * Sets the daily summary for the given day
   *
   * @param DateTime $day
   * @param String $comment
   */
  public function setSummary(DateTime $day, $comment) {
    $q = sprintf('replace into daily_summary (regatta, summary_date, summary) ' .
		 'values ("%s", "%s", "%s")',
		 $this->id, $day->format('Y-m-d'), (string)$comment);
    $this->query($q);
  }

  /**
   * Returns an array of the divisions in this regatta
   *
   * @return list of divisions in this regatta
   */
  public function getDivisions() {
    $q = sprintf('select distinct division from race ' .
		 'where regatta = "%s" ' . 
		 'order by division',
		 $this->id);
    $q = $this->query($q);
    $divs = array();
    while ($row = $q->fetch_object()) {
      $divs[] = Division::get($row->division);
    }
    return $divs;
  }

  /**
   * Gets a list of team objects for this regatta.
   *
   * @return array of team objects
   *
   */
  public function getTeams() {
    $q = sprintf('select team.id, team.name, ' .
		 'school.id as school_id, school.name as school_name, ' .
		 'school.nick_name as school_nick_name, ' .
		 'school.conference as school_conference, ' .
		 'school.city as school_city, school.state as school_state, ' .
		 'school.burgee as school_burgee ' .
		 'from team inner join school on (school.id = team.school) ' .
		 'where regatta = "%s" order by school, id',
		 $this->id);
    $q = $this->query($q);

    $teams = array();
    if ($this->isSingleHanded()) {
      while ($team = $q->fetch_object("SinglehandedTeam")) {
	$teams[] = $team;
	$team->setRpManager($this->rp);
      }
    }
    else {
      while ($team = $q->fetch_object("Team")) {
	$teams[] = $team;
      }
    }
    return $teams;
  }

  /**
   * Adds the given team to this regatta. Updates the given team
   * object to have the correct, databased ID
   *
   * @param Team $team the team to add (only team name and school are
   * needed)
   */
  public function addTeam(Team $team) {
    $q = sprintf('insert into team (regatta, school, name) ' .
		 'values ("%s", "%s", "%s")',
		 $this->id, $team->school->id, $team->name);
    $this->query($q);
    $res = $this->query('select last_insert_id() as id');
    $team->id = $res->fetch_object()->id;
  }

  /**
   * Remove the given team from this regatta
   *
   * @param Team $team the team to remove
   */
  public function removeTeam(Team $team) {
    $q = sprintf('delete from team where id = "%s" and regatta = "%s"', $team->id, $this->id);
    $this->query($q);
  }

  /**
   * Gets the race object for the race with the given division and
   * number. If the race does not exist, throws an
   * InvalidArgumentException. The race object has properties "id",
   * "division", "number", "boat"
   *
   * @param $div the division of the race
   * @param $num the number of the race within that division
   * @return the race object which matches the description
   * @throws InvalidArgumentException if such a race does not exist
   */
  public function getRace(Division $div, $num) {
    $q = sprintf('select %s from %s ' .
		 'where (regatta, division, number) = ' .
		 '      ("%s",    "%s",     "%s") limit 1',
		 Race::FIELDS, Race::TABLES,
		 $this->id, $div, $num);
    $q = $this->query($q);
    if ($q->num_rows == 0) {
      $m = sprintf("No race %s%s in regatta %s", $num, $div, $this->id);
      throw new InvalidArgumentException($m);
    }
    $race = $q->fetch_object("Race");
    $race->addListener($this);
    return $race;
  }

  /**
   * Returns an array of race objects within the specified division
   * ordered by the race number. If no division specified, returns all
   * the races in the regatta ordered by division, then number.
   *
   * @param $div the division whose races to extract
   * @return list of races in that division (could be empty)
   */
  public function getRaces(Division $div = null) {
    if ($div == null) {
      return $this->getAllRaces();
    }
    
    $q = sprintf('select %s from %s ' .
		 'where (regatta, division) = ' .
		 '      ("%s",    "%s") order by number',
		 Race::FIELDS, Race::TABLES,
		 $this->id, $div);
    $q = $this->query($q);
    $races = array();

    while ($race = $q->fetch_object("Race")) {
      $races[] = $race;
      $race->addListener($this);
    }
    return $races;
  }

  /**
   * Returns an array of all the race objects in this regatta ordered
   * by division and number within the division
   *
   * @return list of races in this regatta: (1A, 2A, 1B, 2B, ...)
   *
   */
  private function getAllRaces() {
    $q = sprintf('select %s from %s ' .
		 'where regatta = "%s" ' .
		 'order by division, number',
		 Race::FIELDS, Race::TABLES,
		 $this->id);
    $q = $this->query($q);
    $races = array();
    while ($race = $q->fetch_object("Race"))
      $races[] = $race;
    return $races;
  }

  /**
   * Returns a sorted list of the race numbers common to all the
   * divisions
   *
   * @param Array<Division> the list of divisions
   * @return Array<int> the common race numbers
   */
  public function getCombinedRaces(Array $divs = null) {
    $nums = null;
    if ($divs == null)
      $divs = $this->getDivisions();
    foreach ($this->getDivisions() as $div) {
      $set = array();
      foreach ($this->getRaces($div) as $race)
	$set[] = $race->number;

      $nums = ($nums == null) ? $set : array_intersect($nums, $set);
    }
    return $nums;
  }

  /**
   * Adds the specified race to this regatta. This operation always
   * results in new races being created. The 'id' and 'number'
   * property of the Race object is ignored.
   *
   * @param Race $race the new race to register with this regatta
   */
  public function addRace(Race $race) {
    $q = sprintf('insert into race (regatta, division, boat) ' .
		 'values ("%s", "%s", "%s")',
		 $this->id, $race->division, $race->boat->id);
    $this->query($q);
  }

  /**
   * Removes all the races from the given division
   *
   * @param Division $div the division whose races to remove
   */
  public function removeDivision(Division $div) {
    $q = sprintf('delete from race where (regatta, division) = ("%s", "%s")',
		 $this->id, $div);
    $this->query($q);
  }

  /**
   * Returns a list of races in the given division which are unscored
   *
   * @param Division $div the division. If null, return all unscored races
   * @return Array<Race> a list of races
   */
  public function getUnscoredRaces(Division $div = null) {
    if ($div == null) {
      $list = array();
      foreach ($this->getDivisions() as $div)
	$list = array_merge($list, $this->getUnscoredRaces($div));
      return $list;
    }
    
    $q = sprintf('select %s from %s ' .
		 'where race.division = "%s" ' .
		 '  and race.regatta = "%s" ' .
		 '  and race.id not in ' .
		 '  (select race from finish) ' .
		 'order by number',
		 Race::FIELDS, Race::TABLES, $div, $this->id);
    $q = $this->query($q);
    $list = array();
    while ($obj = $q->fetch_object("Race")) {
      $list[] = $obj;
    }
    return $list;
  }

  /**
   * Get list of scored races in the specified division
   *
   * @param Division $div the division. If null, return all scored races
   * @return Array<Race> a list of races
   */
  public function getScoredRaces(Division $div = null) {
    if ($div == null) {
      $list = array();
      foreach ($this->getDivisions() as $div)
	$list = array_merge($list, $this->getScoredRaces($div));
      return $list;
    }
    
    $q = sprintf('select %s from %s ' .
		 'where race.division = "%s" ' .
		 '  and race.regatta = "%s" ' .
		 '  and race.id in ' .
		 '  (select race from finish) ' .
		 'order by number',
		 Race::FIELDS, Race::TABLES, $div, $this->id);
    $q = $this->query($q);
    $list = array();
    while ($obj = $q->fetch_object("Race")) {
      $list[] = $obj;
    }
    return $list;
  }

  /**
   * Returns a list of the race numbers scored across all divisions
   *
   * @param Array<Division> the divisions
   * @return Array<int> the race numbers
   */
  public function getCombinedScoredRaces(Array $divs = null) {
    if ($divs == null)
      $divs = $this->getDivisions();
    $nums = null;
    foreach ($divs as $div) {
      $set = array();
      foreach ($this->getScoredRaces($div) as $race)
	$set[] = $race->number;

      $nums = ($nums == null) ? $set : array_intersect($nums, $set);
    }
    return $nums;
  }

  /**
   * Returns a list of unscored race numbers common to all divisions
   *
   * @param  Array<Division> the divisions, or all if null
   * @return Array<int> the race numbers
   */
  public function getCombinedUnscoredRaces(Array $divs = null) {
    if ($divs == null)
      $divs = $this->getDivisions();
    $nums = null;
    foreach ($divs as $div) {
      $set = array();
      foreach ($this->getUnscoredRaces($div) as $race)
	$set[] = $race->number;

      $nums = ($nums == null) ? $set : array_intersect($nums, $set);
    }
    return $nums;
  }


  /**
   * Returns the finish for the given race and team, or null
   *
   * @param $race the race object
   * @param $team the team object
   * @return the finish object
   */
  public function getFinish(Race $race, Team $team) {
    $q = sprintf('select finish.id, finish.race, finish.team, finish.entered, ' .
		 'handicap.type as handicap, handicap.amount as h_amt, handicap.comments as h_com, ' .
		 'penalty.type  as penalty,  penalty.comments as p_com, ' .
		 'score.score, score.place, score.explanation ' .
		 'from finish ' .
		 'left join handicap on (finish.id = handicap.finish) ' .
		 'left join penalty  on (finish.id = penalty.finish) ' .
		 'left join score    on (finish.id = score.finish) ' .
		 'where (race, team) = ("%s", "%s")',
		 $race->id, $team->id);
    $q = $this->query($q);
    if ($q->num_rows == 0)
      return null;
    
    $fin = $q->fetch_object();
    $finish = new Finish($fin->id, $race, $team);
    $finish->entered = new DateTime($fin->entered, new DateTimeZone("America/New_York"));;
      
    $penalty = null;
    if ($fin->handicap != null) {
      $penalty = new Breakdown($fin->handicap, $fin->h_amt, $fin->h_com);
    }
    if ($fin->penalty != null) {
      $penalty = new Penalty($fin->penalty, $fin->p_com);
    }
    $finish->penalty   = $penalty;

    // score
    $score = null;
    if ($fin->place != null) {
      $score = new Score($fin->place, $fin->score, $fin->explanation);
    }
    $finish->score = $score;

    $finish->addListener($this);
    return $finish;
  }

  /**
   * Returns an array of finish objects for the given race ordered by
   * timestamp.
   *
   * @param $race whose finishes to get.
   * @return a list of ordered finishes in the race. If null, return
   * all the finishes ordered by race, and timestamp.
   *
   */
  public function getFinishes(Race $race = null) {
    if ($race == null) {
      $list = array();
      foreach ($this->getRaces() as $race)
	$list = array_merge($list, $this->getFinishes($race));
      return $list;
    }
    
    $finishes = array();
    foreach ($this->getTeams() as $team) {
      if (($f = $this->getFinish($race, $team)) !== null)
	$finishes[] = $f;
    }
    
    return $finishes;
  }

  /**
   * Adds the finishes to the regatta, then checks for completeness
   *
   * @param Array<Finish> $finishes the list of finishes
   */
  public function setFinishes(Array $finishes) {
    $fmt = 'replace into finish (race, team, entered) ' .
      'values ("%s", "%s", "%s")';
    foreach ($finishes as $finish) {
      $q = sprintf($fmt,
		   $finish->race->id,
		   $finish->team->id,
		   $finish->entered->format("Y-m-d H:i:s"));
      $this->query($q);
    }

    $this->doScore();
  }

  /**
   * Deletes the finishes in the given race, without scoring the
   * regatta.
   *
   * @param Race $race the race whose finishes to drop
   */
  protected function deleteFinishes(Race $race) {
    $q = sprintf('delete from finish where race = "%s"', $race->id);
    $this->query($q);
  }

  /**
   * Drops all the finishes registered with the given race and
   * rescores the regatta
   *
   * @param Race $race the race whose finishes to drop
   */
  public function dropFinishes(Race $race) {
    $this->deleteFinishes($race);
    $this->doScore();
  }

  /**
   * Set team penalty
   *
   * @param TeamPenalty $penalty the penalty to register
   */
  public function setTeamPenalty(TeamPenalty $penalty) {
    $q = sprintf('replace into %s values ("%s", "%s", "%s", "%s")',
		 TeamPenalty::TABLES,
		 $penalty->team->id,
		 $penalty->division,
		 $penalty->type,
		 $penalty->comments);
    $this->query($q);
  }

  /**
   * Drops the team penalty for the given team in the given division
   *
   * @param Team $team the team whose penalty to drop
   * @param Division $div the division to drop
   */
  public function dropTeamPenalty(Team $team, Division $div) {
    $q = sprintf('delete from %s where (team, division) = ("%s", "%s")',
		 TeamPenalty::TABLES, $team->id, $div);
    $this->query($q);
  }

  /**
   * Returns the team penalty, or null
   *
   * @param Team $team the team
   * @param Division $div the division
   * @return TeamPenalty if one exists, or null otherwise
   */
  public function getTeamPenalty(Team $team, Division $div) {
    $q = sprintf('select %s from %s where team = "%s" and division = "%s"',
		 TeamPenalty::FIELDS, TeamPenalty::TABLES,
		 $team->id, $division);
    $q = $this->query($q);
    if ($q->num_rows == 0)
      return null;
    return $q->fetch_object("TeamPenalty");
  }
  

  /**
   * Returns list of all the team penalties for the given team, or all
   * if null
   *
   * @param Team $team the team whose penalties to return, or all if null
   * @param Division $div the division to fetch, or all if null
   * @return Array<TeamPenalty> list of team penalties
   */
  public function getTeamPenalties(Team $team = null) {
    if ($team == null) {
      $list = array();
      foreach ($this->getTeams() as $team)
	$list = array_merge($list, $this->getTeamPenalties($team, $division));
      return $list;
    }

    $list = array();
    foreach ($this->getDivisions() as $division) {
      $pen = $this->getTeamPenalty($team, $division);
      if ($pen != null) {
	$list[] = $pen;
      }
    }
    return $list;
  }

  /**
   * Returns the timestamp of the last score update
   *
   * @return DateTime, or null if no update found
   */
  public function getLastScoreUpdate() {
    $q = sprintf('select last_update from score_update where regatta = "%s"',
		 $this->id);
    $q = $this->query($q);
    if ($q->num_rows == 0)
      return null;

    return new DateTime($q->fetch_object()->last_update);
  }

  /**
   * Gets the winning team for this regatta.
   * TODO: implement this perhaps in Scorer?
   *
   * @return the winning team object
   */
  public function getWinningTeamID() {
    // Select all
    $q = sprintf('select race.division, race_num.number, race.id, finish.team, ' .
		 'score.place, score.score, score.explanation ' .
		 'from score ' .
		 'inner join finish on (finish.id = score.finish) ' .
		 'inner join race on (race.id = finish.race) ' .
		 'inner join race_num on (race.id = race_num.id) ' .
		 'where race.regatta = "%s"',
		 $this->id);
    
    $scores = $this->query($q);
    
    // Select team scores
    $q = sprintf('select score_team.team, score_team.division, score_team.score ' .
		 'from score_team inner join team on (team.id = score_team.team) ' .
		 'where regatta = "%s"',
		 $this->id);
    $scores_team = $this->query($q);

    // Select team penalties
    $q = sprintf('select team, division, type ' .
		 'from penalty_team ' .
		 'inner join team on (team.id = penalty_team.team) ' .
		 'where team.regatta = "%s"',
		 $this->id);
    $penalties_team = $this->query($q);

    /*
    // Check that there are scores to list
    if ($scores->num_rows > 0) {

      // Parse
      $rd = mysql_column($scores,0);
      $rn = mysql_column($scores,1);
      $ri = mysql_column($scores,2);
      $ts = mysql_column($scores,3);
      $ps = mysql_column($scores,4);
      $ss = mysql_column($scores,5);
      $se = mysql_column($scores,6);

      $ut = mysql_column($scores_team,0);    // scores_team['team']
      $ud = mysql_column($scores_team,1);    // scores_team['division']
      $us = mysql_column($scores_team,2);    // scores_team['score']

      $vt = mysql_column($penalties_team,0); // penalties_team['team']
      $vd = mysql_column($penalties_team,1); // penalties_team['division']
      $vp = mysql_column($penalties_team,2); // penalties_team['type']_

      // Rank
      $ranks = getRankings($ri, $ts, $ss, $ut, $us);
      foreach ($ranks as $team => $score) {
	return $team; // return the first one only
      }
    }
    else {
      return false;
    }
    */
  }

  // ------------------------------------------------------------
  // Scorers
  // ------------------------------------------------------------

  /**
   * Return a list of scorers for this regatta
   *
   * @return Array<Account> a list of scorers
   */
  public function getScorers() {
    $q = sprintf('select %s from %s ' .
		 'inner join host on    (host.account = account.username) ' .
		 'inner join regatta on (host.regatta = regatta.id) ' .
		 'where regatta = "%s"',
		 Account::FIELDS, Account::TABLES, $this->id);
    $q = $this->query($q);
    $list = array();
    while ($obj = $q->fetch_object("Account")) {
      $list[] = $obj;
    }
    return $list;
  }

  /**
   * Returns a list of hosts for this regatta
   *
   * @return Array<Account> a list of hosts
   */
  public function getHosts() {
    $q = sprintf('select %s from %s ' .
		 'inner join host on    (host.account = account.username) ' .
		 'inner join regatta on (host.regatta = regatta.id) ' .
		 'where regatta = "%s" and host.principal = 1',
		 Account::FIELDS, Account::TABLES, $this->id);
    $q = $this->query($q);
    $list = array();
    while ($obj = $q->fetch_object("Account")) {
      $list[] = $obj;
    }
    return $list;
  }

  /**
   * Registers the given scorer with this regatta
   *
   * @param Account $acc the account to add
   * @param bool is_host whether or not this account is also a host
   * for the regatta (default: false)
   */
  public function addScorer(Account $acc, $is_host = false) {
    $q = sprintf('replace into host values ("%s", "%s", %d)',
		 $acc->username, $this->id, ($is_host) ? 1 : 0);
    $this->query($q);
  }

  /**
   * Removes the specified account from this regatta
   *
   * @param Account $acc the account of the scorer to be removed
   * from this regatta
   */
  public function removeScorer(Account $acc) {
    $q = sprintf('delete from host where account = "%s"', $acc->username);
    $q = $this->query($q);
  }

  //------------------------------------------------------------
  // Misc

  /**
   * Get this regatta's ID
   *
   * @return int the regatta's ID
   */
  public function id() { return $this->id; }

  /**
   * Get the MySQLi connection object registered with this regatta
   *
   * @return the MySQLi connection
   */
  public function getConnection() { return $this->con; }

  /**
   * Gets the rotation object that manages this regatta's rotation
   *
   * @return the rotation object for this regatta
   */
  public function getRotation() {
    return $this->rotation;
  }

  /**
   * Gets the RpManager object that manages this regatta's RP
   *
   * @return RpManager the rp manager
   */
  public function getRpManager() {
    return $this->rp;
  }

  /**
   * Returns the day stripped of time-of-day information
   *
   * @param DateTime $time the datetime object
   * @return DateTime the modified datetime object
   */
  public function getDay(DateTime $time) {
    $time_copy = clone($time);
    date_time_set($time_copy, 0, 0, 0);
    return $time_copy;
  }


  /**
   * Determines whether the regatta is a singlehanded regatta or
   * not. Singlehanded regattas consist of one division, and each race
   * consists of single-occupant boats
   *
   * @return boolean is this regatta singlehanded?
   */
  public function isSingleHanded() {
    $divisions = $this->getDivisions();
    if (count($divisions) > 1) return false;

    foreach ($this->getRaces(array_shift($divisions)) as $race) {
      if ($race->boat->occupants > 1)
	return false;
    }
    return true;
  }

  /**
   * Scores itself
   *
   */
  public function doScore() {
    
    // Check that all races are complete:
    $count     = count($this->getTeams());
    $divisions = $this->getDivisions();

    // When scoring combined, the same race number must be scored
    // across all divisions.
    if ($this->get(Regatta::SCORING) == Regatta::SCORING_COMBINED) {

      // start with a list of all the race numbers available. Assumes
      // that each division has the same race numbers. Then remove
      // those race numbers for which any race in any division does
      // not equal the total number of teams
      $numbers = array();
      foreach ($this->getRaces($divisions[0]) as $race)
	$numbers[] = $race->number;

      $faulty = array();
      foreach ($numbers as $num) {
	foreach ($divisions as $div) {
	  $f = $this->getFinishes($this->getRace($div, $num));
	  if (count($f) != $count) {
	    $faulty[] = $num;
	    break;
	  }
	}
      }

      // delete finishes for faulty races
      foreach ($faulty as $num) {
	foreach ($divisions as $div)
	  $this->deleteFinishes($this->getRace($div, $num));
      }
    }
    // With standard scoring, each race is counted individually
    else {
      foreach ($divisions as $div) {
	foreach ($this->getScoredRaces($div) as $race) {
	  if (count($this->getFinishes($race)) != $count)
	    $this->deleteFinishes($race);
	}
      }
    }


    $this->scorer->score($this);

    // Update last score
    $q = sprintf('replace into score_update (regatta) values ("%s")',
		 $this->id);
    $this->query($q);
  }

  // ------------------------------------------------------------
  // Listeners

  /**
   * Commits the properties of the race object. If a race's properties
   * change, this function registers those changes with the database.
   * Note that only the boat and the division are updated.
   *
   * @param Race $race the race to update
   */
  public function changedRace(Race $race) {
    $q = sprintf('update race set boat = "%s", division = "%s" ' .
		 'where id = "%s"',
		 $race->boat->id, $race->division, $race->id);
    $this->query($q);
  }

  /**
   * Commits the changes to the finish
   *
   * @param FinishListener::CONST $type the type of change
   * @param Finish $finish the finish
   */
  public function finishChanged($type, Finish $finish) {

    // Penalties
    if ($type == FinishListener::PENALTY) {
      $q1 = sprintf('delete from penalty  where finish = "%s"', $finish->id);
      $q2 = sprintf('delete from handicap where finish = "%s"', $finish->id);
      $this->query($q1);
      $this->query($q2);

      if ($finish->penalty instanceof Breakdown)
	$q = sprintf('replace into handicap values ("%s", "%s", "%s", "%s")',
		     $finish->id,
		     $finish->penalty->type,
		     $finish->penalty->amount,
		     $finish->penalty->comments);
      else
	$q = sprintf('replace into penalty values ("%s", "%s", "%s")',
		     $finish->id,
		     $finish->penalty->type,
		     $finish->penalty->comments);
      $this->query($q);
      $this->doScore();
    }

    // Scores
    elseif ($type == FinishListener::SCORE) {
      $q = sprintf('replace into score values ("%s", "%s", "%s", "%s")',
		   $finish->id,
		   $finish->score->place,
		   $finish->score->score,
		   $finish->score->explanation);
      $this->query($q);
    }

    // Entered
    elseif ($type == FinishListener::ENTERED) {
      $q = sprintf('update finish set entered = "%s" where id = "%s"',
		   $finish->entered->format("Y-m-d H:i:s"),
		   $finish->id);
      $this->regatta->query($q);
    }
  }

  /**
   * Fetches a list of all the notes for the given race, or the entire
   * regatta if no race provided
   *
   * @return Array<Note> the list of notes
   */
  public function getNotes(Race $race = null) {
    if ($race == null) {
      $list = array();
      foreach ($this->getRaces() as $race) {
	$list = array_merge($list, $this->getNotes($race));
      }
      return $list;
    }

    // Fetch the notes for the given race
    $q = sprintf('select %s from %s where race = "%s"',
		 Note::FIELDS, Note::TABLES, $race->id);
    $q = $this->query($q);

    $list = array();
    while ($obj = $q->fetch_object("Note")) {
      $list[] = $obj;
      $obj->race = $race;
    }
    return $list;
  }

  /**
   * Adds the given note to the regatta. Updates the Note object
   *
   * @param Note $note the note to add and update
   */
  public function addNote(Note $note) {
    $now = new DateTime("now", new DateTimeZone("America/New_York"));
    $q = sprintf('insert into observation (race, observation, observer, noted_at) ' .
		 'values ("%s", "%s", "%s", "%s")',
		 $note->race->id, $note->observation, $now->format("Y-m-d H:i:s"), $note->observer);
    $this->query($q);

    $res = $this->query('select last_insert_id() as id');
    $note->id = $res->fetch_object()->id;
    $note->noted_at = $now;
  }

  /**
   * Deletes the given note from the regatta
   *
   * @param Note $note the note to delete
   */
  public function deleteNote(Note $note) {
    $q = sprintf('delete from observation where id = "%s"', $note->id);
    $this->query($q);
  }
  

  // ------------------------------------------------------------
  // Static methods and properties
  // ------------------------------------------------------------

  private static $static_con;

  /**
   * Sends the given request to the database
   *
   * @param String $query the query to send
   * @return MySQLi_Result the result
   * @throws BadFunctionCallException should the query be unsuccessful.
   */
  protected static function static_query($query) {
    if (!isset(self::$static_con))
      self::$static_con = new MySQLi(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB);
    
    $res = self::$static_con->query($query);
    $error = self::$static_con->error;
    if (!empty($error))
      throw new BadFunctionCallException("Invalid query: $error.");
    return $res;
  }
  
  /**
   * Creates a new regatta with the given specs
   *
   * @param String $db the database to add the regatta to, must be in
   * the database map ($self::DB_MAP)
   * @param String $name the name of the regatta
   * @param DateTime $start_time the start time of the regatta
   * @param DateTime $end_date the end_date
   * @param String $type one of those listed in Preferences::getRegattaTypesAssoc()
   * @param String $comments the comments (default empty)
   *
   * @return int the ID of the regatta
   *
   * @throws InvalidArgumentException if illegal regatta type
   */
  protected static function addRegatta($db,
				       $name,
				       DateTime $start_time,
				       DateTime $end_date,
				       $type,
				       $comments = "") {
    if (!in_array($type, array_keys(Preferences::getRegattaTypeAssoc())))
      throw new InvalidArgumentException("No such regatta type $type.");

    $q = sprintf('insert into regatta ' .
		 '(name, start_time, end_date, type, comments) values ' .
		 '("%s", "%s", "%s", "%s", "%s")',
		 addslashes((string)$name),
		 $start_time->format("Y-m-d H:i:s"),
		 $end_date->format("Y-m-d"),
		 $type,
		 addslashes($comments));

    $res = self::static_query($q);
    
    // Fetch the regatta back
    $last_id = self::static_query('select last_insert_id() as id');
    return $last_id->fetch_object()->id;
  }

  /**
   * Creates a new regatta with the given specs
   *
   * @param String $name the name of the regatta
   * @param DateTime $start_time the start time of the regatta
   * @param DateTime $end_date the end_date
   * @param String $type one of those listed in Preferences::getRegattaTypesAssoc()
   * @param String $comments the comments (default empty)
   *
   * @throws InvalidArgumentException if illegal regatta type
   */
  public static function createRegatta($name,
				       DateTime $start_time,
				       DateTime $end_date,
				       $type,
				       $comments = "") {
    $id = self::addRegatta(SQL_DB, $name, $start_time, $end_date, $type, $comments);
    return new Regatta($id);
  }
}

// Main function
if ($argv[0] == basename(__FILE__)) {
  $reg = new Regatta(115);
  print_r($reg->getCombinedUnscoredRaces());
}
?>