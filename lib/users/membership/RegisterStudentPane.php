<?php
namespace users\membership;

use \model\StudentProfile;
use \ui\CountryStateSelect;
use \ui\ProgressDiv;
use \users\AbstractUserPane;
use \users\utils\RegisterAccountHelper;
use \users\utils\RegistrationEmailSender;

use \Account;
use \DateTime;
use \DB;
use \InvalidArgumentException;
use \Session;
use \STN;
use \Text_Entry;

use \FItem;
use \FOption;
use \FOptionGroup;
use \FReqItem;
use \XA;
use \XEm;
use \XDateInput;
use \XEmailInput;
use \XNumberInput;
use \XP;
use \XPasswordInput;
use \XPort;
use \XRawText;
use \XSelect;
use \XStrong;
use \XSubmitInput;
use \XSubmitP;
use \XTelInput;
use \XTextInput;

/**
 * Allows students to self-register as sailors. This is the entry way
 * to the system as manager of the sailor database.
 *
 * @author Dayan Paez
 * @version 2016-03-24
 */
class RegisterStudentPane extends AbstractUserPane {

  const SUBMIT_INTRO = 'submit-intro';
  const SUBMIT_REGISTER = 'submit-register';
  const SUBMIT_SAILOR_PROFILE = 'submit-sailor-profile';
  const SUBMIT_RESEND = 'submit-resend';
  const SUBMIT_CANCEL = 'submit-cancel';
  const SESSION_KEY = 'sailor-registration';
  const KEY_STAGE = 'stage';
  const KEY_ACCOUNT = 'account';
  const STAGE_INTRO = 1;
  const STAGE_TECHSCORE_ACCOUNT = 2;
  const STAGE_SAILOR_PROFILE = 3;

  const INPUT_EMAIL = 'email';
  const INPUT_TOKEN = 'token';

  private $registrationEmailSender;
  private $registerAccountHelper;

  public function __construct() {
    parent::__construct("Register as a sailor");
  }

  protected function fillHTML(Array $args) {
    echo "<pre>"; print_r($args); "</pre>";
    exit;
    $stage = $this->determineStage($args);
    switch ($stage) {
    case self::STAGE_INTRO:
      $this->fillStageIntro($args);
      break;

    case self::STAGE_TECHSCORE_ACCOUNT:
      $this->fillStageTechscoreAccount($args);
      break;

    case self::STAGE_SAILOR_PROFILE:
      $this->fillStageSailorProfile($args);
      break;

    default:
      throw new InvalidArgumentException(sprintf("Unknown stage provided: %s.", $stage));
    }
  }

  private function fillStageIntro(Array $args) {
    $this->PAGE->addContent($p = new XPort("About sailor registrations"));
    $cont = DB::get(DB::T(DB::TEXT_ENTRY), Text_Entry::SAILOR_REGISTER_MESSAGE);
    if ($cont !== null) {
      $p->add(new XRawText($cont->html));
    }
    $p->add(new XP(array(), array("Registering as a student will automatically create a system account. ", new XStrong("Important:"), " if you already have an account, you do not need to register again. ", new XA($this->linkTo('HomePane'), "Login instead"), " and create a student profile from the user menu.")));
    $p->add($form = $this->createForm());
    $form->add(new XSubmitP(self::SUBMIT_INTRO, "Next →"));
  }

  private function fillStageTechscoreAccount(Array $args) {
    $session = Session::g(self::SESSION_KEY, array());
    $this->PAGE->addContent($p = new XPort(sprintf("%s account", DB::g(STN::APP_NAME))));
    if (array_key_exists(self::KEY_ACCOUNT, $session)) {
      $account = DB::getAccount($session[self::KEY_ACCOUNT]);
      if ($account !== null) {
        $p->add(new XP(array(), "Thank you for registering for an account with TechScore. You should receive an e-mail message shortly with a link to verify your account access."));
        $p->add(new XP(array(),
                       array("If you don't receive an e-mail, please check your junk-mail settings and enable mail from ",
                             new XEm(DB::g(STN::TS_FROM_MAIL)), ".")));
        $p->add($form = $this->createForm());
        $form->add($xp = new XSubmitP(self::SUBMIT_RESEND, "Resend"));
        $xp->add(" ");
        $xp->add(new XSubmitInput(self::SUBMIT_CANCEL, "Cancel"));
        return;
      }
    }
    $p->add($form = $this->createForm());
    
    $form->add(new FReqItem("Email:", new XEmailInput(self::INPUT_EMAIL, "")));
    $form->add(new FReqItem("First name:", new XTextInput("first_name", "")));
    $form->add(new FItem("Middle name:", new XTextInput("middle_name", ""), "Middle initial or full name."));
    $form->add(new FReqItem("Last name:",  new XTextInput("last_name", "")));
    $form->add(new FReqItem("Password:", new XPasswordInput("passwd", "")));
    $form->add(new FReqItem("Confirm password:", new XPasswordInput("confirm", "")));
    $form->add(new XSubmitP(self::SUBMIT_REGISTER, "Register"));
  }

  private function fillStageSailorProfile(Array $args) {
    $this->PAGE->addContent($form = $this->createForm());
    $form->add($p = new XPort("Sailor profile"));
    $p->add(new FReqItem("School:", $this->getSchoolSelect()));
    $currentTime = new DateTime();
    $currentYear = $currentTime->format('Y');
    $p->add(new FReqItem("Graduation Year:", new XNumberInput('graduation_year', '', $currentYear - 1, $currentYear + 6, 1)));
    $p->add(new FReqItem("Date of birth:", new XDateInput('birth_date')));
    $options = array(
      '' => '',
      StudentProfile::FEMALE => "Female",
      StudentProfile::MALE => "Male",
    );
    $p->add(new FReqItem("Gender:", XSelect::fromArray('gender', $options), "To be eligible for women's regattas, you must enter \"Female\"."));

    $form->add($p = new XPort("School year contact"));
    $p->add(new FReqItem("Address line 1:", new XTextInput('contact[school][address_1]', '')));
    $p->add(new FItem("Address line 2:", new XTextInput('contact[school][address_2]', '')));
    $p->add(new FReqItem("City:", new XTextInput('contact[school][city]', '')));
    $p->add(new FReqItem("State:", new CountryStateSelect('contact[school][state]')));
    $p->add(new FReqItem("Postal code:", new XTextInput('contact[school][postal_code]', '')));
    $p->add(new FReqItem("Phone:", new XTelInput('contact[school][telephone]')));
    $p->add(new FItem("Secondary phone:", new XTelInput('contact[school][secondary_telephone]')));
    $p->add(new FItem("Information current until:", new XDateInput('contact[school][current_until]')));

    $form->add($p = new XPort("Home/permanent contact"));
    $p->add(new FReqItem("Email:", new XEmailInput('contact[home][email]', '')));
    $p->add(new FReqItem("Address line 1:", new XTextInput('contact[home][address_1]', '')));
    $p->add(new FItem("Address line 2:", new XTextInput('contact[home][address_2]', '')));
    $p->add(new FReqItem("City:", new XTextInput('contact[home][city]', '')));
    $p->add(new FReqItem("State:", new CountryStateSelect('contact[home][state]')));
    $p->add(new FReqItem("Postal code:", new XTextInput('contact[home][postal_code]', '')));
    $p->add(new FReqItem("Phone:", new XTelInput('contact[home][telephone]')));
    $p->add(new FItem("Information current until:", new XDateInput('contact[home][current_until]')));

    $form->add(new XSubmitP(self::SUBMIT_SAILOR_PROFILE, "Create profile"));
  }

  public function process(Array $args) {
    // Cancel
    if (array_key_exists(self::SUBMIT_CANCEL, $args)) {
      Session::d(self::SESSION_KEY);
      return;
    }

    // Stage intro
    if (array_key_exists(self::SUBMIT_INTRO, $args)) {
      Session::s(self::SESSION_KEY, array(self::KEY_STAGE => self::STAGE_TECHSCORE_ACCOUNT));
      return;
    }

    // Stage account
    if (array_key_exists(self::SUBMIT_REGISTER, $args)) {
      $helper = $this->getRegisterAccountHelper();
      $account = $helper->process($args);

      $existingAccount = DB::getAccountByEmail($account->email);
      if ($existingAccount !== null) {
        if ($existingAccount->status != Account::STAT_REQUESTED) {
          throw new SoterException("Invalid e-mail. Remember, if you already have an account, please log-in to continue.");
        }
        $account->id = $existingAccount->id;
      }

      $account->ts_role = DB::getStudentRole();
      if ($account->ts_role === null) {
        throw new InvalidArgumentException("No student role exists. This should NOT be allowed.");
      }
      $account->role = Account::ROLE_STUDENT;

      $token = $account->createToken();
      $sender = $this->getRegistrationEmailSender();
      if (!$sender->sendRegistrationEmail($token)) {
        throw new SoterException("There was an error with your request. Please try again later.");
      }

      DB::set($account);
      Session::info("New account request processed.");
      Session::s(self::SESSION_KEY, array(self::KEY_STAGE => self::STAGE_TECHSCORE_ACCOUNT, self::KEY_ACCOUNT => $account->id));
      return;
    }

    if (array_key_exists(self::SUBMIT_RESEND, $args)) {
      $session = Session::g(self::SESSION_KEY, array());
      if (!array_key_exists(self::KEY_ACCOUNT, $session)) {
        throw new SoterException("No registration in progress.");
      }
      $account = DB::getAccount($session[self::KEY_ACCOUNT]);
      if ($account == null) {
        throw new SoterException("No registration in progress. Please start again.");
      }
      $token = $account->getToken();
      $sender = $this->getRegistrationEmailSender();
      if (!$sender->sendRegistrationEmail($token)) {
        throw new SoterException("There was an error with your request. Please try again later.");
      }

      Session::info("Activation e-mail sent again.");
      return;
    }

    Session::d(self::KEY_STAGE);
    echo "<pre>"; print_r($args); "</pre>";
    exit;
  }

  private function getSchoolSelect() {
    $aff = new XSelect('school');
    $aff->add(new FOption('0', "[Choose one]"));
    foreach (DB::getConferences() as $conf) {
      $aff->add($opt = new FOptionGroup($conf));
      foreach ($conf->getSchools() as $school) {
        $opt->add(new FOption($school->id, $school->name));
      }
    }
    return $aff;
  }

  /**
   * Helper function also fills out a ProgressDiv. Call once.
   *
   * @param Array $args The arguments.
   */
  private function determineStage(Array $args) {
    $stages = array(
      self::STAGE_INTRO => "Introduction",
      self::STAGE_TECHSCORE_ACCOUNT => "Account",
      self::STAGE_SAILOR_PROFILE => "Sailor Profile",
    );

    $session = Session::g(self::SESSION_KEY, array());
    $currentStage = array_key_exists(self::KEY_STAGE, $session)
      ? $session[self::KEY_STAGE]
      : self::STAGE_INTRO;

    $this->PAGE->addContent($progress = new ProgressDiv());
    $completed = true;
    foreach ($stages as $stage => $title) {
      $link = null;
      $current = false;
      if ($stage == $currentStage) {
        $current = true;
        $completed = false;
      }
      $progress->addStage($title, $link, $current, $completed);
    }
    return $currentStage;
  }

  public function setRegistrationEmailSender(RegistrationEmailSender $sender) {
    $this->registrationEmailSender = $sender;
  }

  protected function getRegistrationEmailSender() {
    if ($this->registrationEmailSender === null) {
      $this->registrationEmailSender = new RegistrationEmailSender(RegistrationEmailSender::MODE_SAILOR);
    }
    return $this->registrationEmailSender;
  }

  public function setRegisterAccountHelper(RegisterAccountHelper $helper) {
    $this->registerAccountHelper = $helper;
  }

  protected function getRegisterAccountHelper() {
    if ($this->registerAccountHelper === null) {
      $this->registerAccountHelper = new RegisterAccountHelper();
    }
    return $this->registerAccountHelper;
  }
}