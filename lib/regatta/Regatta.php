<?php
/*
 * This file is part of TechScore
 *
 * @package regatta
 */

require_once('regatta/DB.php');

/**
 * Encapsulates a (flat) regatta object. Note that comments are
 * suppressed due to speed considerations.
 *
 * @author Dayan Paez
 * @version 2009-11-30
 */
class Regatta extends DBObject {

  /**
   * Standard scoring
   */
  const SCORING_STANDARD = "standard";

  /**
   * Combined scoring
   */   
  const SCORING_COMBINED = "combined";

  /**
   * Team racing
   */
  const SCORING_TEAM = 'team';

  /**
   * Women's regatta
   */
  const PARTICIPANT_WOMEN = "women";
  
  /**
   * Coed regatta (default)
   */
  const PARTICIPANT_COED = "coed";

  /**
   * Gets an assoc. array of the possible regatta types
   *
   * @return Array a dict of regatta types
   */
  public static function getTypes() {
    return array(Regatta::TYPE_CHAMPIONSHIP=>"National Championship",
		 Regatta::TYPE_CONF_CHAMPIONSHIP=>"Conference Championship",
		 Regatta::TYPE_INTERSECTIONAL=>"Intersectional",
		 Regatta::TYPE_TWO_CONFERENCE=>"Two-Conference",
		 Regatta::TYPE_CONFERENCE=>"In-Conference",
		 Regatta::TYPE_PROMOTIONAL=>"Promotional",
		 Regatta::TYPE_PERSONAL=>"Personal");
  }
  const TYPE_PERSONAL = 'personal';
  const TYPE_CONFERENCE = 'conference';
  const TYPE_CHAMPIONSHIP = 'championship';
  const TYPE_INTERSECTIONAL = 'intersectional';
  const TYPE_CONF_CHAMPIONSHIP = 'conference-championship';
  const TYPE_TWO_CONFERENCE = 'two-conference';
  const TYPE_PROMOTIONAL = 'promotional';

  /**
   * Gets an assoc. array of the possible scoring rules
   *
   * @return Array a dict of scoring rules
   */
  public static function getScoringOptions() {
    return array(Regatta::SCORING_STANDARD => "Standard",
		 Regatta::SCORING_COMBINED => "Combined divisions",
		 Regatta::SCORING_TEAM => "Team racing");
  }

  /**
   * Gets an assoc. array of the possible participant values
   *
   * @return Array a dict of scoring rules
   */
  public static function getParticipantOptions() {
    return array(Regatta::PARTICIPANT_COED => "Coed",
		 Regatta::PARTICIPANT_WOMEN => "Women");
  }
  
  // Variables
  public $name;
  public $nick;
  protected $start_time;
  protected $end_date;
  public $type;
  protected $finalized;
  protected $creator;
  protected $venue;
  public $participant;
  public $scoring;

  // Managers
  private $rotation;
  private $rp;
  private $season;
  private $scorer;

  // ------------------------------------------------------------
  // DBObject stuff
  // ------------------------------------------------------------
  public function db_name() { return 'regatta'; }
  protected function db_order() { return array('start_time'=>false); }
  protected function db_cache() { return true; }
  public function db_type($field) {
    switch ($field) {
    case 'start_time':
    case 'end_date':
    case 'finalized':
      return DB::$NOW;
    case 'creator':
      require_once('regatta/Account.php');
      return DB::$ACCOUNT;
    case 'venue':
      return DB::$VENUE;
    default:
      return parent::db_type($field);
    }
  }

  public function &__get($name) {
    switch ($name) {
    case 'scorer':
      if ($this->scorer === null) {
	switch ($this->scoring) {
	case Regatta::SCORING_COMBINED:
	  require_once('regatta/ICSACombinedScorer.php');
	  $this->scorer = new ICSACombinedScorer();
	  break;

	default:
	  require_once('regatta/ICSAScorer.php');
	  $this->scorer = new ICSAScorer();
	}
      }
      return $this->scorer;
    default:
      return parent::__get($name);
    }
  }

  public function getSeason() {
    if ($this->season === null)
      $this->season = Season::forDate($this->__get('start_time'));
    return $this->season;
  }

  /**
   * Fetches the number of days (inclusive) for this event
   *
   * @return int the number of days
   */
  public function getDuration() {
    $start = $this->__get('start_time');
    if ($start === null)
      return 0;
    $start = new DateTime($start->format('r'));
    $start->setTime(0, 0);
    return 1 + floor(($this->__get('end_date')->format('U') - $start->format('U')) / 86400);
  }

  /**
   * Sets the type for this regatta, creating a nick name if needed.
   *
   * Note that it does not actually record the changes in database. A
   * subsequent call to DB::set is necessary.
   *
   * @param Const the regatta type
   * @throws InvalidArgumentException if no regatta can be created
   */
  public function setType($value) {
    $types = Regatta::getTypes();
    if (!isset($types[$value]))
      throw new InvalidArgumentException("Invalid regatta type \"$value\".");
    // re-create the nick name, and let that method determine if it
    // is valid (this would throw an exception otherwise)
    if ($value != Regatta::TYPE_PERSONAL)
      $this->nick = $this->createNick();
    $this->type = $value;
  }

  /**
   * Sets the scoring for this regatta.
   *
   * It is important to use this method instead of setting the scoring
   * directly so that the Regatta can choose the appropriate scorer
   *
   * @param Const the regatta scoring
   */
  public function setScoring($value) {
    if ($value == $this->scoring)
      return;
    $this->scoring = $value;
    $this->scorer = null;
  }

  public function __set($name, $value) {
    if ($name == 'type') {
      $this->setType($value);
      return;
    }
    parent::__set($name, $value);
  }

  // ------------------------------------------------------------
  // Daily summaries
  // ------------------------------------------------------------

  /**
   * Gets the daily summary for the given day
   *
   * @param DateTime $day the day summary to return
   * @return String the summary
   */
  public function getSummary(DateTime $day) {
    $res = DB::getAll(DB::$DAILY_SUMMARY, new DBBool(array(new DBCond('regatta', $this->id), new DBCond('summary_date', $day->format('Y-m-d')))));
    $r = (count($res) == 0) ? '' : $res[0]->summary;
    unset($res);
    return $r;
  }

  /**
   * Sets the daily summary for the given day
   *
   * @param DateTime $day
   * @param String $comment
   */
  public function setSummary(DateTime $day, $comment) {
    // Enforce uniqueness
    $res = DB::getAll(DB::$DAILY_SUMMARY, new DBBool(array(new DBCond('regatta', $this->id), new DBCond('summary_date', $day->format('Y-m-d')))));
    if (count($res) > 0)
      $cur = $res[0];
    else {
      $cur = new Daily_Summary();
      $cur->regatta = $this->id;
      $cur->summary_date = $day;
    }
    $cur->summary = $comment;
    DB::set($cur);
    unset($res);
  }

  /**
   * Returns an array of the divisions in this regatta
   *
   * @return list of divisions in this regatta
   */
  public function getDivisions() {
    $q = DB::prepGetAll(DB::$RACE, new DBCond('regatta', $this->id), array('division'));
    $q->distinct(true);
    $q->order_by(array('division'=>true));
    $q = DB::query($q);
    $divisions = array();
    while ($row = $q->fetch_object()) {
      $divisions[$row->division] = Division::get($row->division);
    }
    return array_values($divisions);
  }

  /**
   * Fetches the team with the given ID, or null
   *
   * @param int $id the id of the team
   * @return Team|null if the team exists
   */
  public function getTeam($id) {
    $res = DB::get($this->isSingleHanded() ? DB::$SINGLEHANDED_TEAM : DB::$TEAM, $id);
    if ($res === null || $res->regatta != $this)
      return null;
    return $res;
  }

  /**
   * Just get the number of teams, which is slightly quicker than
   * serializing all those teams.
   *
   * @return int the fleet size
   */
  public function getFleetSize() {
    return count($this->getTeams());
  }

  /**
   * Gets a list of team objects for this regatta.
   *
   * @param School $school the optional school whose teams to return
   * @return array of team objects
   */
  public function getTeams(School $school = null) {
    $cond = new DBBool(array(new DBCond('regatta', $this->id)));
    if ($school !== null)
      $cond->add(new DBCond('school', $school));
    return DB::getAll($this->isSingleHanded() ? DB::$SINGLEHANDED_TEAM : DB::$TEAM, $cond);
  }

  /**
   * Adds the given team to this regatta. Updates the given team
   * object to have the correct, databased ID
   *
   * @param Team $team the team to add (only team name and school are
   * needed)
   */
  public function addTeam(Team $team) {
    $team->regatta = $this;
    DB::set($team);
  }

  /**
   * Replaces the given team's school information with the team
   * given. Note that this changes the old team's object's
   * information. The new team does not become part of this
   * regatta.
   *
   * @param Team $old the team to replace
   * @param Team $new the team to replace with
   * @throws InvalidArgumentException if old team is not part of this
   * regatta to begin with!
   */
  public function replaceTeam(Team $old, Team $new) {
    if ($old->regatta->id != $this->id)
      throw new InvalidArgumentException("Team \"$old\" is not part of this regatta.");

    $old->school = $new->school;
    $old->name = $new->name;
    DB::set($old);
  }

  /**
   * Remove the given team from this regatta
   *
   * @param Team $team the team to remove
   */
  public function removeTeam(Team $team) {
    DB::remove($team);
  }
  
  /**
   * Returns the simple rank of the teams in the database, by
   * totalling their score across the division given (or all
   * divisions). A tiebreaker procedure should be used after that if
   * multiple teams share the same score.
   *
   * @param Array:Division $divs the divisions to use for the ranking
   */
  public function getRanks(Array $divs) {
    $q = DB::createQuery();
    $q->fields(array(new DBField('team'),
		     new DBField('score', 'sum', 'total')), DB::$FINISH->db_name());
    $q->where(new DBCondIn('race',
			   DB::prepGetAll(DB::$RACE,
					  new DBBool(array(new DBCondIn('division', $divs),
							   new DBCond('regatta', $this->id))),
					  array('id'))));
    $q->order_by(array('total'=>true));
    $q = DB::query($q);
    $ranks = array();
    while ($obj = $q->fetch_object())
      $ranks[] = new Rank($this->getTeam($obj->team), $obj->total);
    $q->free();
    return $ranks;
  }

  /**
   * Gets the race object for the race with the given division and
   * number. If the race does not exist, throws an
   * InvalidArgumentException. The race object has properties "id",
   * "division", "number", "boat"
   *
   * @param $div the division of the race
   * @param $num the number of the race within that division
   * @return Race|null the race object which matches the description
   */
  public function getRace(Division $div, $num) {
    $res = DB::getAll(DB::$RACE, new DBBool(array(new DBCond('regatta', $this->id),
						  new DBCond('division', (string)$div),
						  new DBCond('number', $num))));
    if (count($res) == 0)
      return null;
    $r = $res[0];
    unset($res);
    return $r;
  }

  /**
   * Returns the race that is part of this regatta and has the ID
   *
   * @param String $id the ID
   * @return Race|null the race if it exists
   */
  public function getRaceById($id) {
    $r = DB::get(DB::$RACE, $id);
    if ($r === null || $r->regatta != $this)
      return null;
    return $r;
  }

  /**
   * Return the total number of races participating, for efficiency
   * purposes
   *
   * @return int the number of races
   */
  public function getRacesCount() {
    if ($this->total_races !== null)
      return $this->total_races;
    $this->total_races = count(DB::getAll(DB::$RACE, new DBCond('regatta', $this->id)));
    return $this->total_races;
  }
  private $total_races;

  // ------------------------------------------------------------
  // Races and boats
  // ------------------------------------------------------------

  /**
   * Returns an array of race objects within the specified division
   * ordered by the race number. If no division specified, returns all
   * the races in the regatta ordered by division, then number.
   *
   * @param $div the division whose races to extract
   * @return list of races in that division (could be empty)
   */
  public function getRaces(Division $div = null) {
    $cond = new DBBool(array(new DBCond('regatta', $this->id)));
    if ($div !== null)
      $cond->add(new DBCond('division', (string)$div));
    return DB::getAll(DB::$RACE, $cond);
  }

  /**
   * Returns the unique boats being used in this regatta. Note that
   * this is much faster than going through all the races manually and
   * keeping track of the boats.
   *
   * @param Division $div the division whose boats to retrieve.
   * If null, return all of them instead.
   * @return Array<Boat> the boats
   */
  public function getBoats(Division $div = null) {
    $cond = new DBBool(array(new DBCond('regatta', $this->id)));
    if ($div !== null)
      $cond->add(new DBCond('division', (string)$div));
    return DB::getAll(DB::$BOAT, new DBCondIn('id', DB::prepGetAll(DB::$RACE, $cond, array('boat'))));
  }

  /**
   * Returns a sorted list of the race numbers common to all the
   * divisions
   *
   * @param Array:Division the list of divisions
   * @return Array:int the common race numbers
   */
  public function getCombinedRaces(Array $divs = null) {
    $set = array();
    if ($divs == null)
      $divs = $this->getDivisions();
    foreach ($this->getDivisions() as $div) {
      foreach ($this->getRaces($div) as $race)
	$set[$race->number] = $race->number;
    }
    usort($set);
    return array_values($set);
  }

  /**
   * Adds the specified race to this regatta. Unlike in previous
   * versions, the user needs to specify the race number. As a result,
   * if the race already exists, the code will attempt to update the
   * race instead of adding a new one.
   *
   * @param Race $race the race to register with this regatta
   */
  public function setRace(Race $race) {
    $cur = $this->getRace($race->division, $race->number);
    if ($cur !== null)
      $race->id = $cur->id;
    else
      $this->total_races++;
    $race->regatta = $this;
    DB::set($race);
  }

  /**
   * Removes the specific race from this regatta. Note that in this
   * version, the race is removed by regatta, division, number
   * identifier instead of by ID. This means that it is not necessary
   * to first serialize the race object in order to remove it from the
   * database.
   *
   * It is the client code's responsibility to make sure that there
   * aren't any empty race numbers in the middle of a division, as
   * this could have less than humorous results in the rest of the
   * application.
   *
   * @param Race $race the race to remove
   */
  public function removeRace(Race $race) {
    DB::removeAll(DB::$RACE, new DBBool(array(new DBCond('regatta', $this->id),
					      new DBCond('division', (string)$race->division),
					      new DBCond('number', $race->number))));
  }

  /**
   * Removes all the races from the given division
   *
   * @param Division $div the division whose races to remove
   */
  public function removeDivision(Division $div) {
    DB::removeAll(DB::$RACE, new DBBool(array(new DBCond('regatta', $this->id), new DBCond('division', (string)$div))));
  }

  /**
   * Returns a list of races in the given division which are unscored
   *
   * @param Division $div the division. If null, return all unscored races
   * @return Array<Race> a list of races
   */
  public function getUnscoredRaces(Division $div = null) {
    DB::$RACE->db_set_order(array('number'=>true, 'division'=>true));
    
    $cond = new DBBool(array(new DBCond('regatta', $this->id),
			     new DBCondIn('id', DB::prepGetAll(DB::$FINISH, null, array('race')), DBCondIn::NOT_IN)));
    if ($div !== null)
      $cond->add(new DBCond('division', (string)$div));
    $res = DB::getAll(DB::$RACE, $cond);
    
    DB::$RACE->db_set_order();
    return $res;
  }

  /**
   * Returns a list of the unscored race numbers common to all the
   * divisions passed in the parameter
   *
   * @param Array<div> $divs a list of divisions
   * @return a list of race numbers
   */
  public function getUnscoredRaceNumbers(Array $divisions) {
    $nums = array();
    foreach ($divisions as $div) {
      foreach ($this->getUnscoredRaces($div) as $race)
	$nums[$race->number] = $race->number;
    }
    asort($nums, SORT_NUMERIC);
    return $nums;
  }

  /**
   * Get list of scored races in the specified division
   *
   * @param Division $div the division. If null, return all scored races
   * @return Array<Race> a list of races
   */
  public function getScoredRaces(Division $div = null) {
    $cond = new DBBool(array(new DBCond('regatta', $this->id),
			     new DBCondIn('id', DB::prepGetAll(DB::$FINISH, null, array('race')))));
    if ($div !== null)
      $cond->add(new DBCond('division', (string)$div));
    return DB::getAll(DB::$RACE, $cond);
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
    $nums = array();
    foreach ($divs as $div) {
      foreach ($this->getScoredRaces($div) as $race)
	$nums[$race->number] = $race->number;
    }
    usort($nums);
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
    $nums = array();
    foreach ($divs as $div) {
      foreach ($this->getUnscoredRaces($div) as $race)
	$nums[$race->number] = $race->number;
    }
    sort($nums);
    return $nums;
  }

  /**
   * Fetches the race that was last scored in the regatta, or the
   * specific division if one is provided. This method will look at
   * the timestamp of the first finish in each race to determine which
   * is the latest to be scored.
   *
   * @param Division $div (optional) only look in this division
   * @return Race|null the race or null if none yet scored
   */
  public function getLastScoredRace(Division $div = null) {
    // Get the race (id) from the latest finish
    DB::$FINISH->db_set_order(array('entered'=>false));
    $q = DB::prepGetAll(DB::$FINISH,
			new DBCondIn('race', DB::prepGetAll(DB::$RACE, new DBCond('regatta', $this->id), array('id'))),
			array('race'));
    $q->limit(1);
    $res = DB::query($q);
    if ($res->num_rows == 0)
      $r = null;
    else {
      $res = $res->fetch_object();
      $r = DB::get(DB::$RACE, $res->race);
    }
    unset($res);
    DB::$FINISH->db_set_order();
    return $r;
  }

  // ------------------------------------------------------------
  // Finishes
  // ------------------------------------------------------------

  /**
   * @var Array attempt to cache finishes, index is 'race-team_id'
   */
  private $finishes = array();

  /**
   * Creates a new finish for the given race and team, and returns the
   * object. Note that this clobbers the existing finish, if any,
   * although the information is not saved in the database until it is
   * saved with 'setFinishes'
   *
   * @param Race $race the race
   * @param Team $team the team
   * @return Finish
   */
  public function createFinish(Race $race, Team $team) {
    $id = sprintf('%s-%d', (string)$race, $team->id);
    $fin = new Finish(null, $race, $team);
    $this->finishes[$id] = $fin;
    return $fin;
  }

  /**
   * Returns the finish for the given race and team, or null
   *
   * @param $race the race object
   * @param $team the team object
   * @return the finish object
   */
  public function getFinish(Race $race, Team $team) {
    $id = (string)$race . '-' . $team->id;
    if (isset($this->finishes[$id])) {
      return $this->finishes[$id];
    }
    $res = DB::getAll(DB::$FINISH, new DBBool(array(new DBCond('race', $race), new DBCond('team', $team))));
    if (count($res) == 0)
      $r = null;
    else {
      $r = $res[0];
      $this->finishes[$id] = $r;
    }
    unset($res);
    return $r;
  }

  /**
   * Returns an array of finish objects for the given race ordered by
   * timestamp.
   *
   * @param Race $race whose finishes to get
   * @return Array a list of ordered finishes in the race. If null,
   * return all the finishes ordered by race, and timestamp.
   *
   */
  public function getFinishes(Race $race) {
    return DB::getAll(DB::$FINISH, new DBCond('race', $race));
  }

  /**
   * Returns an array of finish objects for all the races with the
   * same number across all divisions.
   *
   * @param Race $race whose finishes to get
   * @return Array the list of finishes
   */
  public function getCombinedFinishes(Race $race) {
    $races = DB::prepGetAll(DB::$RACE,
			    new DBBool(array(new DBCond('regatta', $this),
					     new DBCond('number', $race->number))),
			    array('id'));
    return DB::getAll(DB::$FINISH, new DBCondIn('race', $races));
  }

  /**
   * Returns all the finishes which have been "penalized" in one way
   * or another. That is, they have either a penalty or a breakdown
   *
   * @return Array:Finish the list of finishes, regardless of race
   */
  public function getPenalizedFinishes() {
    return DB::getAll(DB::$FINISH,
		      new DBBool(array(new DBCondIn('race', DB::prepGetAll(DB::$RACE, new DBCond('regatta', $this->id), array('id'))),
				       new DBCond('penalty', null, DBCond::NE))));
  }

  /**
   * Returns a list of those finishes in the given division which are
   * set to be scored as average of the other finishes in the same
   * division. Confused? Read the procedural rules for breakdowns, etc.
   *
   * @param Division $div the division whose average-scored finishes
   * to fetch
   *
   * @return Array:Finish the finishes
   */
  public function getAverageFinishes(Division $div) {
    return DB::getAll(DB::$FINISH,
		      new DBBool(array(new DBCondIn('race',
						    DB::prepGetAll(DB::$RACE,
								   new DBBool(array(new DBCond('regatta', $this->id),
										    new DBCond('division', (string)$div))),
								   array('id'))),
				       new DBCondIn('penalty', array(Breakdown::BKD, Breakdown::RDG, Breakdown::BYE)),
				       new DBCond('amount', 0, DBCond::LE))));
  }

  /**
   * Like hasFinishes, but checks specifically for penalties
   *
   * @param Race $race optional, if given, returns status for only
   * that race
   * @return boolean
   * @see hasFinishes
   */
  public function hasPenalties(Race $race = null) {
    if ($race === null) {
      return count(DB::getAll(DB::$RACE,
			      new DBBool(array(new DBCond('regatta', $this),					       
					       new DBCondIn('id', DB::prepGetAll(DB::$FINISH,
										 new DBCond('penalty', null, DBCond::NE),
										 array('race'))))))) > 0;
    }
    return count(DB::getAll(DB::$FINISH,
			    new DBBool(array(new DBCond('penalty', null, DBCond::NE),
					     new DBCond('race', $race))))) > 0;
  }

  /**
   * Are there finishes for this regatta?
   *
   * @param Race $race optional, if given, returns status for just
   * that race. Otherwise, the whole regatta
   * @return boolean
   */
  public function hasFinishes(Race $race = null) {
    if ($race === null) {
      return count(DB::getAll(DB::$RACE,
			      new DBBool(array(new DBCond('regatta', $this),
					       new DBCondIn('id', DB::prepGetAll(DB::$FINISH, null, array('race'))))))) > 0;
    }
    return count(DB::getAll(DB::$FINISH, new DBCond('race', $race))) > 0;
  }

  /**
   * Commits the finishes given to the database. Note that the
   * finishes must have been registered ahead of time with the
   * regatta, either through getFinish or createFinish.
   *
   * @param Race $race the race for which to enter finishes
   * @param Array:Finish $finishes the list of finishes
   */
  public function setFinishes(Race $race) {
    $this->commitFinishes($this->getFinishes($race));
  }

  /**
   * Commits the given finishes to the database.
   *
   * @param Array:Finish $finishes the finishes to commit
   * @see setFinishes
   */
  public function commitFinishes(Array $finishes) {
    foreach ($finishes as $finish)
      DB::set($finish);
  }

  /**
   * Deletes the finishes in the given race, without scoring the
   * regatta.
   *
   * @param Race $race the race whose finishes to drop
   */
  protected function deleteFinishes(Race $race) {
    DB::removeAll(DB::$FINISH, new DBCond('race', $race));
  }

  /**
   * Drops all the finishes registered with the given race and
   * rescores the regatta. Respects the regatta scoring option.
   *
   * @param Race $race the race whose finishes to drop
   */
  public function dropFinishes(Race $race) {
    if ($this->scoring == Regatta::SCORING_STANDARD)
      $this->deleteFinishes($race);
    else {
      foreach ($this->getDivisions() as $div)
	$this->deleteFinishes($this->getRace($div, $race->number));
    }
    $this->runScore($race);
  }

  // ------------------------------------------------------------
  // Team penalties
  // ------------------------------------------------------------

  /**
   * Set team penalty
   *
   * @param TeamPenalty $penalty the penalty to register
   */
  public function setTeamPenalty(TeamPenalty $penalty) {
    // Ascertain unique key compliance
    $cur = $this->getTeamPenalty($penalty->team, $penalty->division);
    if ($cur !== null)
      $penalty->id = $cur->id;
    DB::set($penalty);
  }

  /**
   * Drops the team penalty for the given team in the given division
   *
   * @param Team $team the team whose penalty to drop
   * @param Division $div the division to drop
   * @return boolean true if a penalty was dropped
   */
  public function dropTeamPenalty(Team $team, Division $div) {
    $cur = $this->getTeamPenalty($team, $div);
    if ($cur === null)
      return false;
    DB::remove($cur);
    return true;
  }

  /**
   * Returns the team penalty, or null
   *
   * @param Team $team the team
   * @param Division $div the division
   * @return TeamPenalty if one exists, or null otherwise
   */
  public function getTeamPenalty(Team $team, Division $div) {
    $res = $this->getTeamPenalties($team, $div);
    $r = (count($res) == 0) ? null : $res[0];
    unset($res);
    return $r;
  }
  
  /**
   * Returns list of all the team penalties for the given team, or all
   * if null
   *
   * @param Team $team the team whose penalties to return, or all if null
   * @param Division $div the division to fetch, or all if null
   * @return Array:TeamPenalty list of team penalties
   */
  public function getTeamPenalties(Team $team = null, Division $div = null) {
    $cond = new DBBool(array());
    if ($team === null)
      $cond->add(new DBCondIn('team', DB::prepGetAll(DB::$TEAM, new DBCond('regatta', $this->id), array('id'))));
    else
      $cond->add(new DBCond('team', $team));
    if ($div !== null)
      $cond->add(new DBCond('division', (string)$div));
    return DB::getAll(DB::$TEAM_PENALTY, $cond);
  }
  
  /**
   * Returns the timestamp of the last score update
   *
   * @return DateTime, or null if no update found
   */
  public function getLastScoreUpdate() {
    DB::$UPDATE_REQUEST->db_set_order(array('request_time'=>false));
    $res = DB::getAll(DB::$UPDATE_REQUEST, new DBCond('regatta', $this->id));
    $r = (count($res) == 0) ? null : $res[0]->request_time;
    unset($res);
    DB::$UPDATE_REQUEST->db_set_order();
    return $r;
  }

  /**
   * Gets the winning team for this regatta. That is, the team with
   * the lowest score thus far
   *
   * @return Team the winning team object
   */
  public function getWinningTeam() {
    $ranks = $this->__get("scorer")->rank($this);
    if (count($ranks) == 0) return null;
    return $ranks[0]->team;
  }

  /**
   * Like getWinningTeam, this more generic method returns a list of
   * where did every team belonging to the given school finish in this
   * regatta (or is currently finishing). Returns a list because a
   * school can have more than one team per regatta.
   *
   * An empty array means that the school had no teams in this
   * regatta (something which can be known ahead of time using the
   * Season::getParticipation function.
   *
   * @param School $school the school
   * @return Array:int the current or final place finish for all teams
   */
  public function getPlaces(School $school) {
    $ranks = $this->__get("scorer")->rank($this);
    $places = array();
    foreach ($ranks as $i => $rank) {
      if ($rank->team->school->id == $school->id)
	$places[] = ($i + 1);
    }
    return $places;
  }

  // ------------------------------------------------------------
  // Scorers
  // ------------------------------------------------------------

  /**
   * Returns a list of hosts for this regatta
   *
   * @return Array:School a list of hosts
   */
  public function getHosts() {
    return DB::getAll(DB::$SCHOOL,
		      new DBCondIn('id', DB::prepGetAll(DB::$HOST_SCHOOL, new DBCond('regatta', $this->id), array('school'))));
  }

  public function addHost(School $school) {
    // Enforce unique key
    $res = DB::getAll(DB::$HOST_SCHOOL, new DBBool(array(new DBCond('regatta', $this->id), new DBCond('school', $school))));
    if (count($res) > 0)
      return;
    
    $cur = new Host_School();
    $cur->regatta = $this;
    $cur->school = $school;
    DB::set($cur);
    unset($res);
  }

  /**
   * Removes all the host from the regatta. Careful! Each regatta must
   * have at least one host, so do not forget to ::addHost later
   *
   */
  public function resetHosts() {
    DB::removeAll(DB::$HOST_SCHOOL, new DBCond('regatta', $this->id));
  }

  /**
   * Return a list of scorers for this regatta
   *
   * @return Array:Account a list of scorers
   */
  public function getScorers() {
    return DB::getAll(DB::$ACCOUNT, new DBCondIn('id', DB::prepGetAll(DB::$SCORER, new DBCond('regatta', $this->id), array('account'))));
  }

  /**
   * Registers the given scorer with this regatta
   *
   * @param Account $acc the account to add
   */
  public function addScorer(Account $acc) {
    $res = DB::getAll(DB::$SCORER, new DBBool(array(new DBCond('regatta', $this->id), new DBCond('account', $acc))));
    if (count($res) > 0)
      return;
    $cur = new Scorer();
    $cur->regatta = $this->id;
    $cur->account = $acc;
    DB::set($cur);
    unset($res);
  }

  /**
   * Removes the specified account from this regatta
   *
   * @param Account $acc the account of the scorer to be removed
   * from this regatta
   */
  public function removeScorer(Account $acc) {
    DB::removeAll(DB::$SCORER, new DBBool(array(new DBCond('regatta', $this->id), new DBCond('account', $acc))));
  }

  //------------------------------------------------------------
  // Misc
  // ------------------------------------------------------------

  /**
   * Gets the rotation object that manages this regatta's rotation
   *
   * @return the rotation object for this regatta
   */
  public function getRotation() {
    if ($this->rotation === null) {
      require_once('regatta/Rotation.php');
      $this->rotation = new Rotation($this);
    }
    return $this->rotation;
  }

  /**
   * Gets the RpManager object that manages this regatta's RP
   *
   * @return RpManager the rp manager
   */
  public function getRpManager() {
    if ($this->rp === null) {
      require_once('regatta/RpManager.php');
      $this->rp = new RpManager($this);
    }
    return $this->rp;
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

    $res = DB::getAll(DB::$RACE,
		      new DBBool(array(new DBCond('regatta', $this),
				       new DBCondIn('boat', DB::prepGetAll(DB::$BOAT, new DBCond('occupants', 1, DBCond::GT), array('id'))))));
    $r = (count($res) == 0);
    unset($res);
    return $r;
  }

  /**
   * Calls the 'score' method on this regatta's scorer, feeding it the
   * given race. This new method should be used during scoring, as it
   * updates only the one affected race at a time. Whereas the doScore
   * method is more appropriate for input data that needs to be
   * checked first for possible errors.
   *
   * Note that the scorer is responsible for committing the affected
   * finishes back to the database, and so there is no need to
   * explicitly call 'setFinishes' after calling this function.
   *
   * @param Race $race the race to run the score
   */
  public function runScore(Race $race) {
    $this->__get('scorer')->score($this, $race);
  }

  /**
   * Scores the entire regatta
   */
  public function doScore() {
    $scorer = $this->__get('scorer');
    foreach ($this->getScoredRaces() as $race)
      $scorer->score($this, $race);
  }

  // ------------------------------------------------------------
  // Race notes
  // ------------------------------------------------------------

  /**
   * Fetches a list of all the notes for the given race, or the entire
   * regatta if no race provided
   *
   * @return Array:Note the list of notes
   */
  public function getNotes(Race $race = null) {
    if ($race !== null)
      return DB::getAll(DB::$NOTE, new DBCond('race', $race->id));
    $races = array();
    foreach ($this->getRaces() as $race)
      $races[] = $race->id;
    return DB::getAll(DB::$NOTE, new DBCondIn('race', $races));
  }

  /**
   * Adds the given note to the regatta. Updates the Note object
   *
   * @param Note $note the note to add and update
   */
  public function addNote(Note $note) {
    DB::set($note);
  }

  /**
   * Deletes the given note from the regatta
   *
   * @param Note $note the note to delete
   */
  public function deleteNote(Note $note) {
    DB::remove($note);
  }

  /**
   * Creates a regatta nick name for this regatta based on this
   * regatta's name. Nick names are guaranteed to be a unique per
   * season. As such, this function will throw an error if there is
   * already a regatta with the same nick name as this one. This is
   * meant to establish some order from users who fail to read
   * instructions and create mutliple regattas all with the same name,
   * leaving behind "phantom" regattas.
   *
   * Nicknames are all lower case, separated by dashes, and devoid of
   * filler words, including 'trophy', 'championship', and the like.
   *
   * @return String the nick name
   * @throw InvalidArgumentException if the nick name is not unique
   */
  public function createNick() {
    $name = strtolower($this->name);
    // Remove 's from words
    $name = str_replace('\'s', '', $name);

    // Convert dashes, slashes and underscores into spaces
    $name = str_replace('-', ' ', $name);
    $name = str_replace('/', ' ', $name);
    $name = str_replace('_', ' ', $name);

    // White list permission
    $name = preg_replace('/[^0-9a-z\s_+]+/', '', $name);

    // Remove '80th'
    $name = preg_replace('/[0-9]+th/', '', $name);
    $name = preg_replace('/[0-9]*1st/', '', $name);
    $name = preg_replace('/[0-9]*2nd/', '', $name);
    $name = preg_replace('/[0-9]*3rd/', '', $name);

    // Trim and squeeze spaces
    $name = trim($name);
    $name = preg_replace('/\s+/', '-', $name);

    $tokens = explode("-", $name);
    $blacklist = array("the", "of", "for", "and", "an", "in", "is", "at",
		       "trophy", "championship", "intersectional",
		       "college", "university",
		       "professor");
    $tok_copy = $tokens;
    foreach ($tok_copy as $i => $t)
      if (in_array($t, $blacklist))
	unset($tokens[$i]);
    $name = implode("-", $tokens);

    // eastern -> east
    $name = str_replace("eastern", "east", $name);
    $name = str_replace("western", "west", $name);
    $name = str_replace("northern", "north", $name);
    $name = str_replace("southern", "south", $name);

    // semifinals -> semis
    $name = str_replace("semifinals", "semis", $name);
    $name = str_replace("semifinal",  "semis", $name);

    // list of regatta names in the same season as this one
    $season = $this->getSeason();
    if ($season === null)
      throw new InvalidArgumentException("No season for this regatta.");
    foreach ($season->getRegattas() as $n) {
      if ($n->nick == $name && $n->id != $this->id)
	throw new InvalidArgumentException(sprintf("Nick name \"%s\" already in use by (%d).", $name, $n->id));
    }
    return $name;
  }

  // ------------------------------------------------------------
  // Regatta creation
  // ------------------------------------------------------------

  /**
   * Creates a new regatta with the given specs
   *
   * @param String $db the database to add the regatta to, must be in
   * the database map ($self::DB_MAP)
   * @param String $name the name of the regatta
   * @param DateTime $start_time the start time of the regatta
   * @param DateTime $end_date the end_date
   * @param String $type one of those listed in Regatta::getTypes()
   * @param String $participant one of those listed in Regatta::getParticipantOptions()
   * @return int the ID of the regatta
   *
   * @throws InvalidArgumentException if illegal regatta type
   */
  public static function createRegatta($name,
				       DateTime $start_time,
				       DateTime $end_date,
				       $type,
				       $scoring = Regatta::SCORING_STANDARD,
				       $participant = Regatta::PARTICIPANT_COED) {
    $opts = Regatta::getScoringOptions();
    if (!isset($opts[$scoring]))
      throw new InvalidArgumentException("No such regatta scoring $scoring.");
    $opts = Regatta::getParticipantOptions();
    if (!isset($opts[$participant]))
      throw new InvalidArgumentException("No such regatta scoring $scoring.");

    $r = new Regatta();
    $r->name = $name;
    $r->start_time = $start_time;
    $r->end_date = $end_date;
    $r->end_date->setTime(0, 0);
    $r->setScoring($scoring);
    $r->participant = $participant;
    $r->setType($type);
    DB::set($r);
    return $r;
  }

  // ------------------------------------------------------------
  // Comparators
  // ------------------------------------------------------------
  
  /**
   * Compares two regattas based on start_time
   *
   * @param Regatta $r1 a regatta
   * @param Regatta $r2 a regatta
   */
  public static function cmpStart(Regatta $r1, Regatta $r2) {
    if ($r1->start_time < $r2->start_time)
      return -1;
    if ($r1->start_time > $r2->start_time)
      return 1;
    return 0;
  }

  /**
   * Compares two regattas based on start_time, descending
   *
   * @param Regatta $r1 a regatta
   * @param Regatta $r2 a regatta
   */
  public static function cmpStartDesc(Regatta $r1, Regatta $r2) {
    return -1 * self::cmpStart($r1, $r2);
  }
}
DB::$REGATTA = new Regatta();
?>