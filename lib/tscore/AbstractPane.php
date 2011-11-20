<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once('conf.php');
__autoload('XmlLibrary');

/**
 * Parent class of all editing panes. Requires USER and REGATTA.
 *
 * @author Dayan Paez
 * @created 2009-09-27
 */
abstract class AbstractPane {

  // Private variables
  private $name;
  private $announce;
  protected $REGATTA;
  protected $PAGE;
  protected $USER;

  // variables to determine validity. See doActive()
  protected $has_teams = false;
  protected $has_rots = false;
  protected $has_scores = false;
  protected $has_penalty = false;
  
  /**
   * Create a new editing pane with the given name
   *
   * @param String $name the name of the editing page
   * @param User $user the user that is editing
   * @param Regatta $reg the regatta for this page
   */
  public function __construct($name, User $user, Regatta $reg) {
    $this->name = (string)$name;
    $this->REGATTA = $reg;
    $this->USER = $user;

    $rot = $this->REGATTA->getRotation();
    $this->has_teams = count($this->REGATTA->getTeams()) > 1;
    $this->has_rots = $this->has_teams && $rot->isAssigned();
    $this->has_scores = $this->has_teams && $this->REGATTA->hasFinishes();
    $this->has_penalty = $this->has_scores && $this->REGATTA->hasPenalties();
  }

  /**
   * Sets up the HTML page
   *
   */
  protected function setupPage() {
    $title = sprintf("%s | %s | TS",
		     $this->name,
		     $this->REGATTA->get(Regatta::NAME));

    $this->PAGE = new TScorePage($title, $this->USER, $this->REGATTA);
    $this->PAGE->addContent(new PageTitle($this->name));

    // ------------------------------------------------------------
    // Menu
    $score_i = array("Regatta"   => array("settings"   => "DetailsPane",
					  "summaries"  => "SummaryPane",
					  "scorers"    => "ScorersPane",
					  "races"      => "RacesPane",
					  "notes"      => "NotesPane"),
		     "Teams"     => array("teams"      => "TeamsPane",
					  "substitute" => "ReplaceTeamPane",
					  "remove-team"=> "DeleteTeamsPane"),
		     "Rotations" => array("rotations"  => "SailsPane",
					  "tweak-sails"=> "TweakSailsPane",
					  "manual-rotation" => "ManualTweakPane"),
		     "RP Forms"  => array("rp"         => "RpEnterPane",
					  "unregistered" => "UnregisteredSailorPane"),
		     "Finishes"  => array("finishes" => "EnterFinishPane",
					  "drop-finishes" => "DropFinishPane",
					  "penalty"  => "EnterPenaltyPane",
					  "drop-penalty" => "DropPenaltyPane",
					  "team-penalty" => "TeamPenaltyPane"));

    $dial_i  = array("rotation" => "Rotation",
		     "scores"   => "Scores",
		     "sailors"  => "Sailors");

    // Fill panes menu
    $id = $this->REGATTA->id();
    foreach ($score_i as $title => $panes) {
      $menu = new Div();
      $menu->addAttr("class", "menu");
      $menu->addChild(new Heading($title));
      $menu->addChild($m_list = new GenericList());
      foreach ($panes as $url => $pane) {
	$t = $this->doTitle($pane);
	if ($this->doActive($pane))
	  $m_list->addItems(new LItem(new Link("/score/$id/$url", $t)));
	else
	  $m_list->addItems(new LItem($t, array("class"=>"inactive")));
      }
      if ($title == "Regatta")
	$m_list->addItems(new LItem(new Link('/', "Close", array('accesskey'=>'w'))));

      $this->PAGE->addMenu($menu);
    }

    // Downloads
    $menu = new Div();
    $menu->addAttr("class", "menu");
    $menu->addChild(new Heading("Download"));
    $menu->addChild($m_list = new GenericList());
    $m_list->addItems(new LItem(new Link("/download/$id/regatta", "Regatta")));
    $m_list->addItems(new LItem(new Link("/download/$id/rp", "RP Forms")));
    $this->PAGE->addMenu($menu);

    // Dialogs
    $menu = new Div();
    $menu->addAttr("class", "menu");
    $menu->addChild(new Heading("Windows"));
    $menu->addChild($m_list = new GenericList());
    foreach ($dial_i as $url => $title) {
      if ($this->doActive($url)) {
	$link = new Link("/view/$id/$url", $title);
	$link->addAttr("class", "frame-toggle");
	$link->addAttr("target", $url);
	$item = new LItem($link);
      }
      else
	$item = new LItem($title, array("class"=>"inactive"));
      $m_list->addItems($item);
    }
    $this->PAGE->addMenu($menu);
  }

  /**
   * Add the announcements saved in session
   */
  private function processAnnouncements() {
    while (count($_SESSION['ANNOUNCE']) > 0) {
      $anc = Announcement::parse(array_shift($_SESSION['ANNOUNCE']));
      $this->PAGE->addAnnouncement($anc);
    }
  }

  /**
   * Redirects the browser to the specified page, or regatta home if
   * none specified
   *
   *
   */
  protected function redirect($page = null) {
    WebServer::go(sprintf('/score/%s/%s', $this->REGATTA->id(), $page));
  }

  /**
   * Prints string reprensentation of the HTML page
   *
   * @param Array $args the arguments to this page
   */
  final public function getHTML(Array $args) {
    $this->setupPage();
    $this->fillHTML($args);
    if (isset($_SESSION['ANNOUNCE'])) {
      $this->processAnnouncements();
    }
    $this->PAGE->printHTML();
  }

  /**
   * Queues the given announcement
   *
   * @param Announcement $a the announcement to add
   */
  final public function announce(Announcement $a) {
    $_SESSION['ANNOUNCE'][] = $a;
  }

  /**
   * Children of this class must implement this method to be used when
   * displaying the page. The method should fill the protected
   * variable $PAGE, which is an instance of TScorePage
   *
   * @param Array $args arguments to customize the display of the page
   */
  abstract protected function fillHTML(Array $args);

  /**
   * Process the edits described in the arguments array
   *
   * @param Array $args the parameters to process
   * @return Array parameters to pass to the next page
   */
  abstract public function process(Array $args);

  /**
   * Creates a new Form HTML element using the standard URL for this
   * pane. The optional $method is by default post
   *
   * @param $method "post" or "get"
   * @return Form element
   */
  protected function createForm($method = "post") {
    $i = get_class($this);
    if (isset(self::$URLS[$i]))
      return new Form(sprintf("/edit/%d/%s", $this->REGATTA->id(), self::$URLS[$i]), $method);
    throw new InvalidArgumentException("Please register URL for pane $i.");
  }

  /**
   * Returns a new instance of a pane with the given URL
   *
   * @param $url the URL
   * @return AbstractPane|null
   */
  public static function getPane($url, User $r, Regatta $u) {
    switch ($url) {
    case 'home':
    case 'details':
    case 'settings':
      return new DetailsPane($r, $u);
    case 'drop-finishes':
    case 'all-finishes':
    case 'current-finishes':
      return new DropFinishPane($r, $u);
    case 'drop-penalty':
    case 'drop-penalties':
      return new DropPenaltyPane($r, $u);
    case 'enter-finish':
    case 'enter-finishes':
    case 'finish':
    case 'finishes':
      return new EnterFinishPane($r, $u);
    case 'add-penalty':
    case 'penalties':
    case 'penalty':
      return new EnterPenaltyPane($r, $u);
    case 'manual-rotation':
      return new ManualTweakPane($r, $u);
    case 'notes':
    case 'note':
    case 'race-note':
    case 'race-notes':
      return new NotesPane($r, $u);
    case 'races':
    case 'race':
    case 'edit-race':
    case 'edit-races':
      return new RacesPane($r, $u);
    case 'substitute':
    case 'substitute-team':
    case 'sub-team':
      return new ReplaceTeamPane($r, $u);
    case 'rp':
    case 'rps':
    case 'enter-rp':
    case 'enter-rps':
      return new RpEnterPane($r, $u);
    case 'setup-rotations':
    case 'setup-rotation':
    case 'rotation':
    case 'rotations':
    case 'sails':
    case 'create-rotation':
    case 'create-rotations':
      return new SailsPane($r, $u);
    case 'scorer':
    case 'scorers':
      return new ScorersPane($r, $u);
    case 'summaries':
    case 'daily-summaries':
    case 'summary':
    case 'daily-summary':
      return new SummaryPane($r, $u);
    case 'team-penalty':
    case 'team-penalties':
      return new TeamPenaltyPane($r, $u);
    case 'team':
    case 'teams':
    case 'add-teams':
    case 'set-teams':
    case 'add-team':
    case 'set-team':
      return new TeamsPane($r, $u);
    case 'remove-team':
    case 'remove-teams':
    case 'delete-team':
    case 'delete-teams':
      return new DeleteTeamsPane($r, $u);
    case 'tweak':
    case 'tweak-sails':
    case 'substitute-sails':
    case 'substitute-sail':
    case 'tweak-sail':
      return new TweakSailsPane($r, $u);
    case 'unregistered':
    case 'unregistered-sailors':
    case 'unregistered-sailor':
    case 'new-sailors':
    case 'new-sailor':
      return new UnregisteredSailorPane($r, $u);
    default:
      return null;
    }
  }

  private function doActive($class_name) {
    switch ($class_name) {
    case 'DropFinishPane':
    case 'EnterPenaltyPane':
    case 'scores':
      return $this->has_scores;
      
    case 'DropPenaltyPane':
      return $this->has_penalty;
      
    case 'EnterFinishPane':
    case 'ReplaceTeamPane':
    case 'RpEnterPane':
    case 'SailsPane':
    case 'TeamPenaltyPane':
    case 'DeleteTeamsPane':
    case 'UnregisteredSailorPane':
      return $this->has_teams;

    case 'ManualTweakPane':
    case 'TweakSailsPane':
    case 'rotation':
      return $this->has_rots;

    default:
      return true;
    }
  }

  /**
   * Determines whether this pane is active
   *
   * @return boolean true if active, false otherwise
   */
  final public function isActive() {
    return $this->doActive(get_class($this));
  }

  /**
   * Returns the title of this pane
   *
   */
  public function getTitle() {
    return $this->doTitle(get_class($this));
  }
  private function doTitle($i) {
    if (isset(self::$TITLES[$i]))
      return self::$TITLES[$i];
    throw new InvalidArgumentException("No title registered for pane $i.");
  }
  private static $URLS = array("DetailsPane" => "settings",
			       "SummaryPane" => "summaries",
			       "ScorersPane" => "scorers",
			       "RacesPane" => "races",
			       "NotesPane" => "notes",
			       "TeamsPane" => "teams",
			       "DeleteTeamsPane" => "remove-teams",
			       "ReplaceTeamPane" => "substitute",
			       "SailsPane" => "rotations",
			       "TweakSailsPane" => "tweak-sails",
			       "ManualTweakPane" => "manual-rotation",
			       "RpEnterPane" => "rp",
			       "UnregisteredSailorPane" => "unregistered",
			       "EnterFinishPane" => "finishes",
			       "DropFinishPane" => "drop-finishes",
			       "EnterPenaltyPane" => "penalty",
			       "DropPenaltyPane" => "drop-penalty",
			       "TeamPenaltyPane" => "team-penalty");
  
  private static $TITLES = array("DetailsPane" => "Settings",
				 "SummaryPane" => "Summaries",
				 "ScorersPane" => "Scorers",
				 "RacesPane" => "Edit races",
				 "NotesPane" => "Race notes",
				 "TeamsPane" => "Add team",
				 "DeleteTeamsPane" => "Remove team",
				 "ReplaceTeamPane" => "Sub team",
				 "SailsPane" => "Setup",
				 "TweakSailsPane" => "Tweak sails",
				 "ManualTweakPane" => "Manual setup",
				 "RpEnterPane" => "Enter RP",
				 "UnregisteredSailorPane" => "Unregistered",
				 "EnterFinishPane" => "Enter finish",
				 "DropFinishPane" => "All finishes",
				 "EnterPenaltyPane" => "Add penalty",
				 "DropPenaltyPane" => "Drop penalty",
				 "TeamPenaltyPane" => "Team penalty");
}

?>