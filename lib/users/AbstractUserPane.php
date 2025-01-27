<?php
namespace users;

use \ui\Pane;
use \utils\RouteManager;
use \utils\Context;
use \xml5\MainMenuList;

use \Account;
use \Conf;
use \DB;
use \Email_Token;
use \Permission;
use \PermissionException;
use \STN;
use \School;
use \Session;
use \SoterException;
use \TScorePage;
use \WS;

use \XA;
use \XDiv;
use \XFileForm;
use \XForm;
use \XH4;
use \XHiddenInput;
use \XImg;
use \XLi;
use \XLinkCSS;
use \XOl;
use \XP;
use \XPage;
use \XPageTitle;
use \XPort;
use \XRawScript;
use \XScript;
use \XUl;

require_once('xml5/TS.php');

/**
 * This is the parent class of all user's editing panes. It insures a
 * function called processGET() exists which only populates a page if so
 * necessary. This page is modeled after tscore/AbstractPane.
 *
 * @author Dayan Paez
 * @version 2010-04-12
 */
abstract class AbstractUserPane implements Pane {

  protected $USER;
  protected $PAGE;
  protected $SCHOOL;
  protected $title;
  /**
   * @var RouteManager the manager to use for pane resolution.
   *
   * Initialized by getRouteManager.
   */
  private static $routeManager;

  /**
   * Creates a new User editing pane with the given title
   *
   * @param String $title the title of the page
   * @param Account $user the user to whom this applies
   */
  public function __construct($title, Account $user = null) {
    $this->title = (string)$title;
    $this->USER  = $user;
    if ($this->USER !== null)
      $this->SCHOOL = $this->USER->getFirstSchool();
  }

  /**
   * Retrieves the HTML code for this pane
   *
   * @param Array $args the arguments to consider
   * @return String the HTML code
   */
  public function processGET(Array $args) {
    require_once('xml5/TScorePage.php');
    $this->PAGE = new TScorePage($this->title, $this->USER);

    if ($this->USER === null) {
      // ------------------------------------------------------------
      // menu
      $panes = array(
        'HomePane',
        'users\RegisterPane',
        'users\membership\RegisterStudentPane',
      );
      $menu = array();
      foreach ($panes as $pane) {
        if ($this->isAvailable($pane)) {
          $link = WS::link('/' . $this->pane_url($pane));
          $href = $this->pane_title($pane);
          $menu[$link] = $href;
        }
      }

      if (count($menu) > 0) {
        $this->PAGE->addMenu(new MainMenuList("Register", $menu));
      }

      $menu = array(
        Conf::$PUB_HOME => "Public site",
      );
      if (($n = DB::g(STN::ORG_NAME)) !== null && ($u = DB::g(STN::ORG_URL)) !== null) {
        $menu[$u] = sprintf("%s Website", $n);
      }
      $menu['http://techscore.sourceforge.net'] = "Offline Techscore";
      $this->PAGE->addMenu(new MainMenuList("Useful links", $menu));

      $this->PAGE->addContent(new XPageTitle($this->title));
      $this->fillHTML($args);
      $this->PAGE->printXML();
      return;
    }

    // ------------------------------------------------------------
    // menu

    $menus = array(
      'Regattas' => array(
        'HomePane',
        'UserSeasonPane',
        'UserArchivePane',
        'NewRegattaPane',
        'users\super\GlobalSettings',
      ),

      'Membership' => array(
        'users\membership\StudentProfilePane',
        'users\membership\EligibilityPane',
        'users\membership\ConferencePane',
        'users\membership\SchoolsPane',
        'users\membership\SailorsPane',
        'users\membership\UnregisteredSailorsPane',
        'users\membership\DatabaseSyncManagement',
        'users\membership\MembershipSettingsPane',
      ),

      'Reports' => array(
        'AllAmerican',
        'CompareHeadToHead',
        'SchoolParticipationReportPane',
        'MembershipReport',
        'BillingReport',
      ),

      'Settings' => array(
        'OrganizationConfiguration',
        'VenueManagement',
        'BoatManagement',
        'RegattaTypeManagement',
        'TeamRaceOrderManagement',
        'SeasonManagement',
        'users\admin\AutoFinalizePane',
      ),

      'Messaging' => array(
        'SendMessage',
        'MailingListManagement',
        'users\admin\EmailTemplateManagement',
      ),

      'Users' => array(
        'PendingAccountsPane',
        'AccountsPane',
        'LoggedInUsers',
        'RoleManagementPane',
        'PermissionManagement',
        'users\admin\RegistrationSettingsPane',
      ),

      'Public site' => array(
        'SocialSettingsManagement',
        'SponsorsManagement',
        'PublicFilesManagement',
        'users\admin\TextManagement',
        'QueuedUpdates',
      ),
    );

    foreach ($menus as $title => $items) {
      $list = array();
      foreach ($items as $pane) {
        if ($this->isAvailable($pane) && $this->isPermitted($pane)) {
          $list[WS::link('/' . $this->pane_url($pane))] = $this->pane_title($pane);
        }
      }
      
      if (count($list) > 0) {
        $this->PAGE->addMenu(new MainMenuList($title, $list));
      }
    }

    $this->PAGE->addContent(new XPageTitle($this->title));
    $this->fillHTML($args);
    $this->PAGE->printXML();
  }

  /**
   * Redirects to the given URL, or back to the referer
   *
   * @param String $url the url to go
   */
  protected function redirect($url = null, Array $args = array()) {
    if ($url !== null)
      WS::go(WS::link('/'.$url, $args));
    WS::goBack('/');
  }

  protected function redirectTo($classname = null, Array $args = array(), $anchor = null) {
    if ($anchor !== null && $anchor[0] != '#') {
      $anchor = '#' . $anchor;
    }
    return WS::go(WS::link('/' . $this->pane_url($classname), $args, $anchor));
  }

  /**
   * Creates a link to this pane with optional GET arguments
   *
   * @param Array $args the optional list of parameters
   * @return String the link
   */
  protected function link(Array $args = array()) {
    return $this->linkTo(null, $args);
  }

  /**
   * Creates a link to given pane with optional GET arguments.
   *
   * @param String $classname null to use "this" pane.
   * @param Array $args the optional list of parameters.
   * @param String $anchor the page anchor (sans #).
   * @return String the link.
   */
  protected function linkTo($classname = null, Array $args = array(), $anchor = null) {
    if ($anchor !== null && $anchor[0] != '#') {
      $anchor = '#' . $anchor;
    }
    return WS::link('/' . $this->pane_url($classname), $args, $anchor);
  }

  /**
   * Creates a new form HTML element using the page_name attribute
   *
   * @param Const $method XForm::POST or XForm::GET
   * @return XForm
   */
  protected function createForm($method = XForm::POST) {
    $form = new XForm('/'.$this->pane_url(), $method);
    if ($method == XForm::POST) {
      $this->addCsrfToken($form);
    }
    return $form;
  }

  protected function createFileForm() {
    $form = new XFileForm('/'.$this->pane_url());
    $this->addCsrfToken($form);
    return $form;
  }

  protected function addCsrfToken(XForm $form) {
    if (class_exists('Session')) {
      $form->add(new XHiddenInput('csrf_token', Session::getCsrfToken()));
    }
  }

  protected function enableRecaptcha(XForm $form) {
    $submitAttrs = array();
    if (($siteKey = DB::g(STN::RECAPTCHA_SITE_KEY))) {
      $this->PAGE->setDoctype(XPage::HTML_5);
      $this->PAGE->head->add(new XScript('text/javascript', 'https://www.google.com/recaptcha/api.js', null, array('async'=>'async', 'defer'=>'defer')));
      $this->PAGE->head->add(new XRawScript('text/javascript', null, 'function submitRecaptchaForm() { document.getElementById("recaptcha-form").submit(); }'));
      $form->set('id', 'recaptcha-form');
      $submitAttrs["class"] = 'g-recaptcha';
      $submitAttrs["data-sitekey"] = $siteKey;
      $submitAttrs["data-callback"] = 'submitRecaptchaForm';
    }
    return $submitAttrs;
  }

  protected function validateRecaptcha(Array $args) {
    if ($recaptchaSecretKey = DB::g(STN::RECAPTCHA_SECRET_KEY)) {
      $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HEADER, false);
      curl_setopt($ch, CURLOPT_USERAGENT, 'TS3');
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, array(
        'secret' => $recaptchaSecretKey,
        'response' => DB::$V->incString($args, 'g-recaptcha-response'),
        'remoteip' => $_SERVER['REMOTE_ADDR'],
      ));

      $output = json_decode(curl_exec($ch), true);
      if (!array_key_exists('success', $output) || $output['success'] !== true) {
        throw new SoterException("Request failed the web bot challenge.");
      }
    }
  }

  /**
   * Wrapper around process method to be used by web clients. Wraps
   * the SoterExceptions as announcements.
   *
   * @param Array $args the parameters to process
   * @return Array parameters to pass to the next page
   */
  public function processPOST(Array $args) {
    try {
      $token = DB::$V->reqString($args, 'csrf_token', 10, 100, "Invalid request provided (missing CSRF)");
      if ($token !== Session::getCsrfToken())
        throw new SoterException("Stale form. For your security, please try again.");
      return $this->process($args);
    } catch (SoterException $e) {
      Session::error($e->getMessage());
      return $args;
    }
  }

  /**
   * Fill this page's content
   *
   * @param Array $args the arguments to process
   */
  abstract protected function fillHTML(Array $args);

  /**
   * Processes the requests made to this page (usually from this page)
   *
   * @param Array $args the arguments to process
   * @return Array the modified arguments
   */
  abstract public function process(Array $args);

  // ------------------------------------------------------------
  // Static methods
  // ------------------------------------------------------------

  /**
   * Fetches the pane based on URL
   *
   * @param Array $uri the URL tokens, in order
   * @param Account $u the responsible account
   * @return AbstractUserPane the specified pane
   * @throws PaneException if malformed request
   * @throws PermissionException if insufficient permissions
   */
  public static function getPane(Array $uri, Account $u) {
    $base = array_shift($uri);

    // ------------------------------------------------------------
    // Handle the rest
    // ------------------------------------------------------------
    $pane = self::pane_from_url($base);
    if ($pane === null)
      throw new PaneException(sprintf("Invalid page requested (%s).", $base));
    $path = self::pane_path($pane);
    if ($path !== null) {
      require_once($path . '/' . $pane . '.php');
    }
    $obj = new $pane($u);
    if (!$obj->isPermitted())
      throw new PermissionException("No access to requested page.");
    if (!$obj->isAvailable()) {
      throw new PaneException(sprintf("Invalid page requested (%s).", $base));
    }
    return $obj;
  }

  // ------------------------------------------------------------
  // Routing setup
  // ------------------------------------------------------------

  /**
   * Returns the name of the (first) pane for given URL
   *
   * @param String the URL to match
   * @return String|null the classname of matching pane
   */
  public static function pane_from_url($url) {
    return self::getRouteManager()->getClassnameFromUrl($url);
  }

  /**
   * Returns the path to the classname in question
   *
   * @param String $classname leave null to use current class
   * @return String
   * @throws InvalidArgumentException if unknown classname provided
   */
  protected static function pane_path($classname) {
    if ($classname === null)
      $classname = get_called_class();
    return self::getRouteManager()->getPath($classname);
  }

  /**
   * Initializes route manager, and returns it.
   *
   * @return RouteManager.
   */
  private static function getRouteManager() {
    if (self::$routeManager == null) {
      self::$routeManager = new RouteManager();
      self::$routeManager->loadFile(RouteManager::ROUTE_USER);
    }
    return self::$routeManager;
  }

  /**
   * Returns the canonical URL for pane identified by classname
   *
   * @param String $classname leave null to use current class
   * @return String the URL (sans leading /)
   * @throws InvalidArgumentException if unknown classname provided
   */
  final public function pane_url($classname = null) {
    if ($classname === null)
      $classname = get_class($this);

    $context = new Context();
    $context->setSchool($this->SCHOOL);
    return self::getRouteManager()->makeUrl($classname, $context);
  }

  /**
   * Returns the label to use for pane identified by classname
   *
   * @param String $classname leave null to use current class
   * @return String
   * @throws InvalidArgumentException if unknown classname provided
   */
  public function pane_title($classname = null) {
    if ($classname === null)
      $classname = get_class($this);
    return self::getRouteManager()->getName($classname);
  }

  protected function setupTextEditors(Array $ids) {
    $this->PAGE->head->add(new XLinkCSS('text/css', WS::link('/inc/css/preview.css'), 'screen', 'stylesheet'));
    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/DPEditor.js')));
    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/DPEditorUI.js')));

    $script = 'window.addEventListener("load", function(e){';
    foreach ($ids as $id)
      $script .= sprintf('new DPEditor("%s", false).uiInit();', $id);
    $script .= '}, false);';
    $this->PAGE->head->add(new XScript('text/javascript', null, $script));
  }

  /**
   * Is this pane at all available in this installation?
   *
   * @param String $classname leave null to use current class
   * @return boolean true if pane is available according to route manager.
   * @throws InvalidArgumentException if unknown classname provided
   */
  public function isAvailable($classname = null) {
    if ($classname === null) {
      $classname = get_class($this);
    }
    $callback = self::getRouteManager()->getIsAvailableCallback($classname);
    if ($callback === null) {
      return true;
    }
    return $callback->isAvailable();
  }

  /**
   * Does this pane's user have access?
   *
   * @param String $classname leave null to use current class
   * @return boolean true if access to any of pane's list of permissions
   * @throws InvalidArgumentException if unknown classname provided
   */
  public function isPermitted($classname = null) {
    if ($classname === null)
      $classname = get_class($this);
    $permissions = self::getRouteManager()->getPermissions($classname);

    if (count($permissions) == 0)
      return true;

    // Limit the list of permissions if schools involved
    // The permissions below require school affiliation
    if ($this->SCHOOL === null) {
      $perms = array();
      foreach ($permissions as $perm) {
        if (!in_array($perm, array(
                        Permission::EDIT_SCHOOL_LOGO,
                        Permission::EDIT_UNREGISTERED_SAILORS,
                        Permission::EDIT_TEAM_NAMES,
                        Permission::PARTICIPATE_IN_REGATTA,
                        Permission::EDIT_REGATTA,
                        Permission::FINALIZE_REGATTA,
                        Permission::CREATE_REGATTA,
                        Permission::DELETE_REGATTA)))
          $perms[] = $perm;
      }
      if (count($perms) == 0)
        return false;
      return $this->USER->canAny($perms);
    }
    return $this->USER->canAny($permissions);
  }
}
