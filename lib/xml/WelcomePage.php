<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once('xml/XmlLibrary.php');
require_once('xml/TScorePage.php');

/**
 * Welcome page, subclasses WebPage
 *
 */
class WelcomePage extends TScorePage {

  // Private variables
  private $header;
  private $navigation;
  private $menu;
  private $content;
  private $announce;

  /**
   * Create a new Welcome webpage, titled "Welcome"
   *
   */
  public function __construct() {
    parent::__construct("Welcome to TechScore");
    $this->fillMenu();
    $this->fillContent();
  }

  /**
   * Fills the menu
   *
   */
  protected function fillMenu() {
    // Access to registration, ICSA, offline TS
    $this->addMenu(new XDiv(array('class'=>'menu'),
			    array(new XH4("Useful Links"),
				  new XUl(array(),
					  array(new XLi(new XA(".", "Sign-in")),
						new XLi(new XA("register", "Register")),
						new XLi(new XA("http://www.collegesailing.org", "ICSA Website")),
						new XLi(new XA("http://techscore.sourceforge.net", "Offline TechScore")))))));
  }

  /**
   * Sets up the body of this page
   *
   */
  protected function fillContent() {
    // LOGIN MENU
    $this->addContent($p = new Port("Sign-in"));
    $p->add($form = new XForm("/login", XForm::POST));
    $form->add(new FItem("Username:", new FText("userid", "", array("maxlength"=>"40"))));
    $form->add($fi = new FItem("Password:", new XPasswordInput("pass", "", array("maxlength"=>"48"))));
    $fi->add(new XMessage(new XA('/password-recover', "Forgot your password?")));

    $form->add(new FSubmit("login", "Login"));

    // Announcements
    $this->addContent($p = new Port("Announcements"));
    $file = sprintf("%s/announcements.html", dirname(__FILE__));
    if (file_exists($file))
      $p->add(new XRawText(file_get_contents($file)));
    else
      $p->add(new XP(array(), "No announcements at this time."));

    $this->addContent($p = new Port("Register for TechScore"));
    $p->add(new XP(array(),
		   array("If you are affiliated with ",
			 new XA("http://www.collegesailing.org", "ICSA"),
			 " and would like an account with TechScore, you can ",
			 new XA("/register", "register here"), ".")));
  }
}

?>
