<?php
/*
 * This file is part of TechScore
 */

require_once('mysqli/DBM.php');

/**
 * Database serialization manager for all of TechScore.
 *
 * @author Dayan Paez
 * @version 2012-01-07
 * @package dbm
 */
class DB extends DBM {

  // Template objects
  const AA_REPORT = 'AA_Report';
  const ACCOUNT = 'Account';
  const ACCOUNT_CONFERENCE = 'Account_Conference';
  const ACCOUNT_SCHOOL = 'Account_School';
  const ACTIVE_SCHOOL = 'Active_School';
  const ACTIVE_TYPE = 'Active_Type';
  const ANSWER = 'Answer';
  const BOAT = 'Boat';
  const BURGEE = 'Burgee';
  const COACH = 'Coach';
  const CONFERENCE = 'Conference';
  const DAILY_SUMMARY = 'Daily_Summary';
  const DT_RP = 'Dt_Rp';
  const DT_TEAM_DIVISION = 'Dt_Team_Division';
  const EMAIL_TOKEN = 'Email_Token';
  const FINISH = 'Finish';
  const FINISH_MODIFIER = 'FinishModifier';
  const FULL_REGATTA = 'FullRegatta';
  const HOST_SCHOOL = 'Host_School';
  const MEMBER = 'Member';
  const MERGE_LOG = 'Merge_Log';
  const MERGE_REGATTA_LOG = 'Merge_Regatta_Log';
  const MERGE_SAILOR_LOG = 'Merge_Sailor_Log';
  const MESSAGE = 'Message';
  const NOTE = 'Note';
  const NOW = 'DateTime';
  const OUTBOX = 'Outbox';
  const PERMISSION = 'Permission';
  const PUB_FILE = 'Pub_File';
  const PUB_FILE_SUMMARY = 'Pub_File_Summary';
  const PUBLIC_REGATTA = 'Public_Regatta';
  const PUB_REGATTA_URL = 'Pub_Regatta_Url';
  const PUB_SPONSOR = 'Pub_Sponsor';
  const QUESTION = 'Question';
  const RACE_ORDER = 'Race_Order';
  const RACE = 'Race';
  const RANKED_SINGLEHANDED_TEAM = 'RankedSinglehandedTeam';
  const RANKED_TEAM = 'RankedTeam';
  const REGATTA_DOCUMENT = 'Document';
  const REGATTA_DOCUMENT_RACE = 'Document_Race';
  const REGATTA_DOCUMENT_SUMMARY = 'Document_Summary';
  const REGATTA = 'Regatta';
  const REPRESENTATIVE = 'Representative';
  const ROLE_PERMISSION = 'Role_Permission';
  const ROLE = 'Role';
  const ROUND_GROUP = 'Round_Group';
  const ROUND = 'Round';
  const ROUND_SEED = 'Round_Seed';
  const ROUND_SLAVE = 'Round_Slave';
  const ROUND_TEMPLATE = 'Round_Template';
  const RP_ENTRY = 'RPEntry';
  const RP_FORM = 'RP_Form';
  const RP_LOG = 'RP_Log';
  const SAILOR = 'Sailor';
  const SAILOR_SEASON = 'Sailor_Season';
  const SAIL = 'Sail';
  const SCHOOL = 'School';
  const SCHOOL_SEASON = 'School_Season';
  const SCORER = 'Scorer';
  const SEASON = 'Season';
  const SETTING = 'STN';
  const SINGLEHANDED_TEAM = 'SinglehandedTeam';
  const SYNC_LOG = 'Sync_Log';
  const TEAM_NAME_PREFS = 'Team_Name_Prefs';
  const TEAM_PENALTY = 'TeamPenalty';
  const TEAM_ROTATION = 'TeamRotation';
  const TEAM = 'Team';
  const TEXT_ENTRY = 'Text_Entry';
  const TYPE = 'Type';
  const UPDATE_CONFERENCE = 'UpdateConferenceRequest';
  const UPDATE_FILE = 'UpdateFileRequest';
  const UPDATE_REQUEST = 'UpdateRequest';
  const UPDATE_SCHOOL = 'UpdateSchoolRequest';
  const UPDATE_SEASON = 'UpdateSeasonRequest';
  const VENUE = 'Venue';
  const WEBSESSION = 'Websession';

  // The validation engine
  public static $V = null;

  // Template object stash
  public static $T = array();

  public static function T($name) {
    if (!array_key_exists($name, self::$T)) {
      if (!class_exists($name, true))
        throw new RuntimeException("Unable to find class $name.");
      self::$T[$name] = new $name();
    }
    return self::$T[$name];
  }

  public static function setConnectionParams($host, $user, $pass, $db) {

    DBM::setConnectionParams($host, $user, $pass, $db);

    require_once('regatta/TSSoter.php');
    self::$V = new TSSoter();
    self::$V->setDBM('DB');
  }

  /**
   * Returns the conference with the given ID
   *
   * @param String $id the id of the conference
   * @return Conference the conference object
   */
  public static function getConference($id) {
    return self::get(self::T(DB::CONFERENCE), $id);
  }

  /**
   * Returns a list of conference objects
   *
   * @return a list of conferences
   */
  public static function getConferences() {
    return self::getAll(self::T(DB::CONFERENCE));
  }

  /**
   * Returns the school with the given ID, or null if none exists
   *
   * @return School|null $school with the given ID
   */
  public static function getSchool($id) {
    return self::get(self::T(DB::SCHOOL), $id);
  }

  /**
   * Returns list of all schools
   *
   * @param boolean $active true (default) to only return active ones
   */
  public static function getSchools($active = true) {
    $obj = ($active) ? DB::T(DB::ACTIVE_SCHOOL) : DB::T(DB::SCHOOL);
    return self::getAll($obj);
  }

  /**
   * Sets the inactive flag on all the schools in the DB.
   *
   * @param Season $season the season for which to inactivate
   */
  public static function inactivateSchools(Season $season) {
    $q = self::createQuery(DBQuery::UPDATE);
    $q->values(array(new DBField('inactive')),
               array(DBQuery::A_STR),
               array(DB::T(DB::NOW)->format('Y-m-d H:i:s')),
               DB::T(DB::SCHOOL)->db_name());
    $q->where(new DBCond('inactive', null));
    self::query($q);
  }

  /**
   * Returns a list of available boats
   *
   * @return Array<Boat> list of boats
   */
  public static function getBoats() {
    return self::getAll(self::T(DB::BOAT));
  }

  /**
   * Fetches the boat with the given ID
   *
   * @param int $id the ID of the boat
   * @return Boat|null
   */
  public static function getBoat($id) {
    return self::get(self::T(DB::BOAT), $id);
  }

  /**
   * Returns the venue object with the given ID
   *
   * @param String $id the id of the object
   * @return Venue the venue object, or null
   */
  public static function getVenue($id) {
    return self::get(self::T(DB::VENUE), $id);
  }

  /**
   * Get a list of registered venues.
   *
   * @return Array of Venue objects
   */
  public static function getVenues($start = null, $end = null) {
    return self::getAll(self::T(DB::VENUE));
  }

  /**
   * Gets the first Role designated as "is_default"
   *
   * @return Role should always return a Role
   */
  public static function getDefaultRole() {
    $res = self::getAll(self::T(DB::ROLE), new DBCond('is_default', 1));
    return (count($res) == 0) ? null : $res[0];
  }

  /**
   * Perform keyword replacement using given account
   *
   * @param String $mes the template message
   * @param Account $to the account whose values to replace in message
   * @param School $school the school involved (optional)
   * @return String the replaced message
   */
  public static function keywordReplace($mes, Account $to, School $school = null) {
    $mes = str_replace('{FIRST_NAME}', $to->first_name, $mes);
    $mes = str_replace('{LAST_NAME}', $to->last_name, $mes);
    $mes = str_replace('{ROLE}', ucfirst($to->role), $mes);
    $mes = str_replace('{FULL_NAME}', $to->getName(), $mes);
    $mes = str_replace('{SCHOOL}',    $school, $mes);
    return $mes;
  }

  /**
   * Convenience method to e-mail out a Message object
   *
   * @param Message $message the e-mail to send
   * @return boolean result of sending e-mail
   * @see mail
   */
  public static function mailMessage(Message $message) {
    self::mail(
      $message->account->email,
      $message->subject,
      $message->content,
      true,
      array('Reply-To' => sprintf('%s <%s>', $message->sender, $message->sender->email)),
      array(),
      $message->read_token
    );
  }

  /**
   * Sends a generic mail message to the given user with the given
   * subject, appending the correct headers (i.e., the "from"
   * field). This method uses the standard PHP mail function
   *
   * @param String $to the e-mail address to send to
   * @param String $subject the subject
   * @param String $body the body of the message, will be wrapped to
   * 72 characters
   * @param boolean $wrap whether to wrap message (default = true)
   * @param Array $extra_headers optional map of extra headers to send
   * @param Array $attachments list of Attachment objects or file paths
   * @param String $read_token a token to embed in the HTML version of message
   * @return boolean the result, as returned by mail
   */
  public static function mail($to, $subject, $body, $wrap = true, Array $extra_headers = array(), Array $attachments = array(), $read_token = null) {
    if ($wrap)
      $body = wordwrap($body, 72);

    require_once('xml5/TEmailMessage.php');
    $page = new TEmailMessage($subject, $read_token);

    require_once('xml5/TSEditor.php');
    $parser = new TSEditor();
    foreach ($parser->parse($body) as $elem)
      $page->append($elem);

    $parts = array(
      'text/plain; charset=utf8' => $body,
      'text/html; charset=utf8' => $page->toXML(),
    );

    return self::multipartMail($to, $subject, $parts, $extra_headers, $attachments);
  }

  /**
   * Sends a multipart (MIME) mail message to the given user with the
   * given subject, appending the correct headers (i.e., the "from"
   * field). This method uses the standard PHP mail function
   *
   * @param String|Array $to the e-mail address(es) to send to
   * @param String $subject the subject
   * @param Array $parts the different MIME parts, indexed by MIME type.
   * @param Array $extra_headers optional map of extra headers to send
   * @param Array $attachments list of Attachment objects or file paths
   * @return boolean the result, as returned by mail
   * @see TSMailer::multipartMail
   */
  public static function multipartMail($to, $subject, Array $parts, Array $extra_headers = array(), Array $attachments = array()) {
    require_once('mail/TSMailer.php');
    require_once('mail/Attachment.php');
    foreach ($attachments as $i => $file) {
      if (!($file instanceof Attachment))
        $attachments[$i] = new Attachment($file);
    }
    return TSMailer::sendMultipart($to, $subject, $parts, $extra_headers, $attachments);
  }

  /**
   * Get all non-completed outgoing messages
   *
   * @return Array:Outbox the messages
   */
  public static function getPendingOutgoing() {
    return self::getAll(self::T(DB::OUTBOX), new DBCond('completion_time', null));
  }

  // ------------------------------------------------------------
  // Messages
  // ------------------------------------------------------------

  /**
   * Retrieves the message with the given ID. Note that the Message
   * class is not auto-loaded. Using this method ascertains that the
   * class is loaded, and that DB::T(DB::MESSAGE) is not null.
   *
   * @param String $id the id of the message to retrieve
   * @return Message|null the message, if any
   */
  public static function getMessage($id) {
    return self::get(self::T(DB::MESSAGE), $id);
  }

  /**
   * Retrieves all messages with given read_token
   *
   * @param String $token the read_token to search
   * @return Array:Message
   */
  public static function getMessagesWithReadToken($token) {
    return self::getAll(self::T(DB::MESSAGE), new DBCond('read_token', $token));
  }

  /**
   * Retrieve all messages for the given account in order
   *
   * @param Account $acc the account
   */
  public static function getUnreadMessages(Account $acc) {
    self::T(DB::MESSAGE)->db_set_order(array('created'=>true));
    $l = self::getAll(self::T(DB::MESSAGE), new DBBool(array(new DBCond('account', $acc->id), new DBCond('read_time', null))));
    self::T(DB::MESSAGE)->db_set_order();
    return $l;
  }

  /**
   * Adds the given message for the given user
   *
   * @param Account $from the sender
   * @param Account $acc the recipient
   * @param String $sub the subject of the message
   * @param String $mes the message
   * @param boolean $email true to send e-mail message
   * @return Message the queued message
   */
  public static function queueMessage(Account $from, Account $acc, $sub, $con, $email = false) {
    $mes = new Message();
    $mes->sender = $from;
    $mes->account = $acc;
    $mes->subject = $sub;
    $mes->content = $con;

    if ($email !== false) {
      $mes->read_token = sha1(uniqid(true));
      self::mailMessage($mes);
    }

    self::set($mes, false);
    return $mes;
  }

  /**
   * Marks the given message as read using the current timestamp or
   * the one provided. Updates the Message object
   *
   * @param Message $mes
   * @param DateTime $time
   */
  public static function markRead(Message $mes, DateTime $time = null) {
    $mes->read_time = ($time === null) ? self::T(DB::NOW) : $time;
    self::set($mes);
  }

  /**
   * Deletes the message (actually, marks it as "inactive")
   *
   * @param Message $mes the message to "delete"
   */
  public static function deleteMessage(Message $mes) {
    $mes->inactive = 1;
    self::set($mes);
  }

  /**
   * Sends mail to the authorities on behalf of the user
   *
   * @param Message $mes the message being replied
   * @param String $reply the reply
   */
  public static function reply(Message $mes, $reply) {
    $body = $reply . "\n\n> " . str_replace("\n", "\n> ", $mes->content);
    $to = ($mes->sender === null) ? DB::g(STN::TS_FROM_MAIL) : $mes->sender->email;
    $res = self::mail($to, sprintf("Re: %s", $mes->subject), $body, true, array('Reply-To' => $mes->account->email));
  }

  // ------------------------------------------------------------
  // Sailors
  // ------------------------------------------------------------

  /**
   * Fetches the Sailor with the given ID
   *
   * @param int $id the ID of the person
   * @return Sailor|null the sailor
   */
  public static function getSailor($id) {
    return DB::get(DB::T(DB::SAILOR), $id);
  }

  /**
   * Fetches the registered Sailor with the given ID
   *
   * @param int $id the ID of the registered sailor
   * @return Sailor|null the sailor
   */
  public static function getRegisteredSailor($id) {
    $r = DB::getAll(DB::T(DB::MEMBER), new DBCond('icsa_id', $id));
    $s = (count($r) == 0) ? null : $r[0];
    unset($r);
    return $s;
  }

  /**
   * Searches for the sailor's first, last, or full name
   *
   * @param String $str the string to search
   * @param mixed $registered true|false to filter, or anything else
   * to ignore registration status
   */
  public static function searchSailors($str, $registered = 'all') {
    $q = self::prepSearch(self::T(DB::SAILOR), $str, array('first_name', 'last_name', 'concat(first_name, " ", last_name)'));
    if ($registered === true)
      $q->where(new DBCond('icsa_id', null, DBCond::NE));
    elseif ($registered === false)
      $q->where(new DBCond('icsa_id', null));
    return new DBDelegate(self::query($q), new DBObject_Delegate(get_class(DB::T(DB::SAILOR))));
  }

  // ------------------------------------------------------------
  // Account management
  // ------------------------------------------------------------

  /**
   * Returns the account with the given id
   *
   * @return Account the account with the given id, null if none
   * exist
   */
  public static function getAccount($id) {
    return self::get(self::T(DB::ACCOUNT), $id);
  }

  /**
   * Returns account with given e-mail/username
   *
   * @return Account the account with the given email, null if none
   */
  public static function getAccountByEmail($email) {
    $res = self::getAll(DB::T(DB::ACCOUNT), new DBCond('email', $email));
    return (count($res) > 0) ? $res[0] : null;
  }

  /**
   * Determines whether e-mail sent is being used already
   *
   * @param String $email the email to verify
   * @return boolean
   */
  public static function isAccountEmailAvailable($email) {
    return (self::getAccountByEmail($email) === null);
  }

  /**
   * Create a new hash for the given user using the plain-text password.
   *
   * @param Account $user the user
   * @param String $passwd the plain text password
   */
  public static function createPasswordHash(Account $user, $passwd) {
    return hash('sha512', $user->email . "\0" . sha1($passwd) . "\0" . Conf::$PASSWORD_SALT);
  }

  /**
   * Returns all the pending users, using the given optional indices
   * to limit the list, like the range function in Python.
   *
   * @param int $start the start index (inclusive)
   * @param int $end   the end index (exclusive)
   * @return Array:Account
   */
  public static function getPendingUsers() {
    return self::getAll(self::T(DB::ACCOUNT), new DBCond('status', Account::STAT_PENDING));
  }

  /**
   * Returns just the administrative users
   *
   * @return Array:Account
   */
  public static function getAdmins() {
    return self::getAll(self::T(DB::ACCOUNT), new DBBool(array(new DBCond('status', Account::STAT_ACTIVE),
                                                         new DBCond('admin', 0, DBCond::GT))));
  }

  /**
   * Fetches the (first) account which has the recovery token provided.
   *
   * NOTE: client should check that the account's token is still active
   *
   * @param String $hash the hash
   * @return Account|null the matching account or null if none match
   * @see Account::isTokenActive
   */
  public static function getAccountFromToken($hash) {
    $token = DB::get(DB::T(DB::EMAIL_TOKEN), $hash);
    if ($token === null)
      return null;
    return $token->account;
  }

  /**
   * Returns a list of accounts fulfilling the given role
   *
   * @param String|null $role a possible Account role
   * @param String|null $status a possible Account status
   * @param Role|null $ts_role the role to limit by
   * @return Array:Account the list of accounts
   * @throws InvalidArgumentException if provided role is invalid
   */
  public static function getAccounts($role = null, $status = null, Role $ts_role = null) {
    $cond = null;
    if ($role !== null) {
      $roles = Account::getRoles();
      if (!isset($roles[$role]))
        throw new InvalidArgumentException("Invalid role provided: $role.");
      $cond = new DBCond('role', $role);
    }
    if ($status !== null) {
      $statuses = Account::getStatuses();
      if (!isset($statuses[$status]))
        throw new InvalidArgumentException("Invalid status provided: $status.");
      if ($cond === null)
        $cond = new DBCond('status', $status);
      else
        $cond = new DBBool(array($cond, new DBCond('status', $status)));
    }
    if ($ts_role !== null) {
      if ($cond === null)
        $cond = new DBCond('ts_role', $ts_role->id);
      else
        $cond = new DBBool(array($cond, new DBCond('ts_role', $ts_role->id)));
    }
    return self::getAll(self::T(DB::ACCOUNT), $cond);
  }

  /**
   * Search accounts, with optional role and/or status filter
   *
   * @param String|null $role a possible Account role
   * @param String|null $status a possible Account status
   * @param Role|null $ts_role limit to those roles
   * @return Array:Account the list of accounts
   * @throws InvalidArgumentException if provided role is invalid
   */
  public static function searchAccounts($qry, $role = null, $status = null, Role $ts_role = null) {
    $fields = array('first_name', 'last_name', 'id', 'concat(first_name, " ", last_name)');
    if ($role === null && $status === null && $ts_role === null)
      return self::search(DB::T(DB::ACCOUNT), $qry, $fields);

    $cond = new DBBool(array());
    if ($role !== null) {
      $roles = Account::getRoles();
      if (!isset($roles[$role]))
        throw new InvalidArgumentException("Invalid role provided: $role.");
      $cond->add(new DBCond('role', $role));
    }
    if ($status !== null) {
      $statuses = Account::getStatuses();
      if (!isset($statuses[$status]))
        throw new InvalidArgumentException("Invalid status provided: $status.");
      $cond->add(new DBCond('status', $status));
    }
    if ($ts_role !== null) {
      $cond->add(new DBCond('ts_role', $ts_role->id));
    }

    $q = self::prepSearch(DB::T(DB::ACCOUNT), $qry, $fields);
    $q->where($cond);
    $r = self::query($q);
    return new DBDelegate($r, new DBObject_Delegate(get_class(DB::T(DB::ACCOUNT))));
  }

  /**
   * Checks that the account holder is active. Otherwise, redirect to
   * license. Otherwise, redirect out
   *
   * @param Account $user the user to check
   * @throws InvalidArgumentException if invalid parameter
   * @TODO this should be migrated to using account
   */
  public static function requireActive(Account $user) {
    switch ($user->status) {
    case Account::STAT_ACTIVE:
      return;

    case Account::STAT_ACCEPTED:
      WS::go('/license');

    default:
    case Account::STAT_INACTIVE:
      WS::go('/logout');
    }
  }

  /**
   * Returns the boat that designated as the default for the school
   *
   * @param School $school the school whose default boat to fetch
   * @return Boat the boat
   */
  public static function getPreferredBoat(School $school) {
    $res = self::getAll(self::T(DB::BOAT));
    $r = (count($res) == 0) ? null : $res[0];
    unset($res);
    return $r;
  }

  // ------------------------------------------------------------
  // Utilities
  // ------------------------------------------------------------

  /**
   * Creates array of range from string.
   *
   * Expects argument to contain only spaces, commas, dashes and
   * numbers, greater than 0
   *
   * @param String $str the range to parse
   * @return Array the numbers in the string in numerical order
   */
  public static function parseRange($str) {
    // Check for valid characters
    if (preg_match('/[^0-9 ,-]/', $str) == 1)
      return array();

    // Remove leading and trailing spaces, commasn and hyphens
    $str = preg_replace('/^[ ,-]*/', '', $str);
    $str = preg_replace('/[ ,-]*$/', '', $str);
    $str = preg_replace('/ +/', ' ', $str);

    // Squeeze spaces
    $str = preg_replace('/ +/', ' ', $str);

    // Make interior spaces into commas, and squeeze commas
    $str = str_replace(" ", ",", $str);
    $str = preg_replace('/,+/', ',', $str);

    // Squeeze hyphens
    $str = preg_replace('/-+/', '-', $str);

    if (strlen($str) == 0)
      return array();

    $sub = explode(",", $str);
    $list = array();
    foreach ($sub as $s) {
      $delims = explode("-", $s);
      $start  = $delims[0];
      $end    = $delims[count($delims)-1];

      // Check limits
      if ($start > $end) // invalid range
        return null;
      for ($i = $start; $i <= $end; $i++)
        $list[] = (int)$i;
    }

    return array_unique($list);
  }

  /**
   * Creates a string representation of the integers in the list
   *
   * @param Array<int> $list the numbers to be made into a range
   * @return String the range as a string
   */
  public static function makeRange(Array $list) {
    // Must be unique and sorted
    sort($list, SORT_NUMERIC);
    $list = array_unique($list);
    if (count($list) == 0)
      return "";

    $range_start = null;
    $last = null;
    $range = "";
    foreach ($list as $next) {
      if ($last === null) {
        $range .= $next;
        $range_start = $next;
      }
      elseif ($next != $last + 1) {
        if ($range_start != $last)
          $range .= "-$last";
        $range .= ",$next";
        $range_start = $next;
      }
      $last = $next;
    }
    if ($range_start != $last)
      $range .= "-$last";
    return $range;
  }

  /**
   * Return a human-readable representation of time difference
   *
   * @param DateTime $timestamp the timestamp in question
   * @param DateTime $relative current time
   * @return String e.g. "about 5 minutes ago"
   */
  public static function howLongFrom(DateTime $timestamp, DateTime $relative = null) {
    if ($relative === null)
      $relative = DB::T(DB::NOW);
    $interval = $relative->diff($timestamp);
    $result = self::howLong($interval);
    if ($result == '1 day')
      return ($interval->invert) ? "yesterday" : "tomorrow";
    if ($interval->invert)
      return $result . " ago";
    return "in " . $result;
  }

  /**
   * Format a time interval as a human-readable string
   *
   * @param DateTime $relative current time
   * @return String e.g. "about 5 minutes ago"
   */
  public static function howLong(DateInterval $interval) {
    if ($interval->y > 1)
      return sprintf("more than %d years", $interval->y);
    if ($interval->y == 1)
      return "more than a year";
    if ($interval->m > 1)
      return sprintf("%d months", $interval->m);
    if ($interval->d > 1)
      return sprintf("%d days", $interval->d);
    if ($interval->d == 1)
      return "1 day";
    if ($interval->h > 0)
      return sprintf("%d hour%s", $interval->h, ($interval->h > 1) ? "s" : "");
    if ($interval->i > 55)
      return "about an hour";
    if ($interval->i > 1)
      return sprintf("%d minutes", $interval->i);
    if ($interval->i > 0)
      return "a minute";
    return "less than a minute";
  }

  /**
   * Perfect for transition, creates ONE and only ONE regatta
   *
   * @param String $id the regatta ID
   * @return Regatta the regatta object
   * @throws InvalidArgumentException if illegal value
   */
  public static function getRegatta($id) {
    return DB::get(DB::T(DB::REGATTA), $id);
  }

  /**
   * Returns the season with the given ID or shortString, or null.
   *
   * @param String $id the ID or shortString of the season
   * @return Season|null
   */
  public static function getSeason($id) {
    $res = DB::get(DB::T(DB::SEASON), $id);
    if ($res !== null)
      return $res;

    $res = DB::getAll(DB::T(DB::SEASON), new DBCond('url', $id));
    return (count($res) == 0) ? null : $res[0];
  }

  /**
   * Fetches any existing race order that matches the given fields
   *
   * @param String $num_divisions (3 is the usual)
   * @param String $num_teams the number of teams
   * @param String $num_boats the number of boats
   * @param Const $frequency one of Race_Order::FREQUENCY_*
   * @param Array $master the distribution of teams to carry over
   */
  public static function getRaceOrder($num_divisions, $num_teams, $num_boats, $frequency, Array $master = null) {
    $master_teams = null;
    if ($master !== null && count($master) > 0)
      $master_teams = implode("\0", $master);
    $r = DB::getAll(DB::T(DB::RACE_ORDER),
                    new DBBool(array(new DBCond('num_teams', $num_teams),
                                     new DBCond('master_teams', $master_teams),
                                     new DBCond('num_boats', $num_boats),
                                     new DBCond('frequency', $frequency),
                                     new DBCond('num_divisions', $num_divisions))));
    if (count($r) == 0)
      return null;
    return $r[0];
  }

  /**
   * Fetches all the race order templates for given parameters
   *
   * @param int $num_teams how many teams in the template
   * @param int $num_divisions how many divisions
   * @return Array:Race_Order
   */
  public static function getRaceOrders($num_teams, $num_divisions) {
    return DB::getAll(DB::T(DB::RACE_ORDER),
                      new DBBool(array(new DBCond('num_teams', $num_teams),
                                       new DBCond('num_divisions', $num_divisions))));
  }

  /**
   * Get files with the given filename
   *
   * @param String $name the name to search
   * @return Pub_File|null
   */
  public static function getFile($name) {
    return DB::get(DB::T(DB::PUB_FILE), $name);
  }

  /**
   * Return files whose name match filter provided
   *
   * @param String $filter SQL-compliant string
   * @param boolean $full set to true to get Pub_File
   * @return Array:Pub_File_Summary
   */
  public static function getFilesLike($filter, $full = false) {
    $obj = ($full !== false) ? DB::T(DB::PUB_FILE_SUMMARY) : DB::T(DB::PUB_FILE);
    return DB::getAll($obj, new DBCond('id', $filter, DBCond::LIKE));
  }

  /**
   * Creates a suitable URL from given string
   *
   * @param String $seed the input
   * @param boolean $apply_rule_c false to NOT remove short words
   * @param Array $blacklist additional words to remove
   * return String the URL-safe equivalent
   */
  public static function slugify($seed, $apply_rule_c = true, Array $blacklist = array()) {
    // remove spaces, ('s)'s
    $url = strtolower($seed);
    $url = str_replace('\'s', '', $url);
    $url = str_replace('/', '-', $url);
    $url = str_replace(' ', '-', $url);
    $url = str_replace('_', '-', $url);

    // remove unwarranted characters and squeeze dashes
    $url = preg_replace('/[^a-z0-9-]/', '', $url);
    $url = preg_replace('/-+/', '-', $url);

    // short words and blacklist
    $tokens = explode('-', $url);
    $copy = $tokens;
    foreach ($copy as $i => $token) {
      if (in_array($token, $blacklist) || ($apply_rule_c && strlen($token) < 2))
        unset($tokens[$i]);
    }
    $tokens = implode('-', $tokens);
    if (strlen($tokens) < 3)
      return $url;
    return $tokens;
  }

  // ------------------------------------------------------------
  // Settings
  // ------------------------------------------------------------

  public static function g($key) {
    $attrs = self::getSettingNames();
    if (!in_array($key, $attrs))
      throw new InvalidArgumentException("Invalid setting $key.");
    if (!array_key_exists($key, self::$settings)) {
      self::$settings[$key] = null;
      $res = DB::get(DB::T(DB::SETTING), $key);
      if ($res !== null && $res->value !== null)
        self::$settings[$key] = $res->value;
      else
        self::$settings[$key] = STN::getDefault($key);
    }
    return self::$settings[$key];
  }

  public static function s($key, $value) {
    $attrs = self::getSettingNames();
    if (!in_array($key, $attrs))
      throw new InvalidArgumentException("Invalid setting $key.");
    if ($value !== null)
      $value = (string)$value;

    $res = DB::get(DB::T(DB::SETTING), $key);
    $upd = true;
    if ($res === null) {
      $res = new STN();
      $res->id = $key;
      $upd = false;
    }
    $res->value = $value;
    DB::set($res, $upd);
    self::$settings[$key] = $value;
  }

  public static function getSettingNames() {
    if (self::$setting_names === null) {
      $r = new ReflectionClass(DB::T(DB::SETTING));
      self::$setting_names = $r->getConstants();
    }
    return self::$setting_names;
  }
  private static $settings = array();
  private static $setting_names;

  /**
   * Fetches the form to use for the given regatta
   *
   * @return AbstractRpForm the form, if any
   */
  public static function getRpFormWriter(FullRegatta $reg) {
    $divisions = count($reg->getDivisions());
    if ($reg->scoring == Regatta::SCORING_TEAM) {
      $form = self::g(STN::RP_TEAM_RACE);
    }
    elseif ($reg->isSingleHanded()) {
      $form = self::g(STN::RP_SINGLEHANDED);
    }
    elseif ($divisions == 2) {
      $form = self::g(STN::RP_2_DIVISION);
    }
    elseif ($divisions == 3) {
      $form = self::g(STN::RP_3_DIVISION);
    }
    elseif ($divisions == 4) {
      $form = self::g(STN::RP_4_DIVISION);
    }
    elseif ($divisions == 1) {
      $form = self::g(STN::RP_1_DIVISION);
    }
    else
      throw new InvalidArgumentException("Regattas of this type are not supported.");

    if ($form === null)
      return null;

    require_once(sprintf('rpwriter/%s.php', $form));
    return new $form();
  }

  // ------------------------------------------------------------
  // Tweet
  // ------------------------------------------------------------

  private static $twitterer = false;

  /**
   * Wrapper around TwitterWriter
   *
   * @param String $mes
   */
  public static function tweet($mes) {
    if (self::$twitterer === false) {
      $ck = DB::g(STN::TWITTER_CONSUMER_KEY);
      $cs = DB::g(STN::TWITTER_CONSUMER_SECRET);
      $ot = DB::g(STN::TWITTER_OAUTH_TOKEN);
      $os = DB::g(STN::TWITTER_OAUTH_SECRET);
      if ($ck === null || $cs === null || $ot === null || $os === null)
        self::$twitterer = null;
      else {
        require_once('twitter/TwitterWriter.php');
        self::$twitterer = new TwitterWriter($ck, $cs, $ot, $os);
      }
    }
    if (self::$twitterer !== null)
      self::$twitterer->tweet($mes);
  }
}