<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/super/AbstractSuperUserPane.php');

/**
 * Manage the global settings for this installation of Techscore
 *
 * @author Dayan Paez
 * @created 2013-11-24
 */
class GlobalSettings extends AbstractSuperUserPane {

  public function __construct(Account $user) {
    parent::__construct("Global settings", $user);
    $this->page_url = 'conf';
  }

  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("General parameters"));
    $p->add($f = $this->createForm());

    $f->add(new FItem("Application Name:", new XTextInput(STN::APP_NAME, DB::g(STN::APP_NAME), array('maxlength'=>50))));
    $f->add(new FItem("Version:", new XTextInput(STN::APP_VERSION, DB::g(STN::APP_VERSION))));
    $f->add(new FItem("Copyright:", new XTextInput(STN::APP_COPYRIGHT, DB::g(STN::APP_COPYRIGHT))));
    $f->add(new FItem("Send e-mails from:", new XTextInput(STN::TS_FROM_MAIL, DB::g(STN::TS_FROM_MAIL))));

    $f->add(new FItem("Divert e-mails to:", new XInput('email', STN::DIVERT_MAIL, DB::g(STN::DIVERT_MAIL)), "For production, this value should be blank"));

    $f->add(new FItem("Sailor API URL:", new XInput('url', STN::SAILOR_API_URL, DB::g(STN::SAILOR_API_URL), array('size'=>60))));
    $f->add(new FItem("Coach API URL:", new XInput('url', STN::COACH_API_URL, DB::g(STN::COACH_API_URL), array('size'=>60))));
    $f->add(new FItem("School API URL:", new XInput('url', STN::SCHOOL_API_URL, DB::g(STN::SCHOOL_API_URL), array('size'=>60))));

    $f->add(new FItem("Help base URL:", new XInput('url', STN::HELP_HOME, DB::g(STN::HELP_HOME), array('size'=>60))));

    $f->add(new XSubmitP('set-params', "Save changes"));
  }

  public function process(Array $args) {
    if (isset($args['set-params'])) {
      $changed = false;

      foreach (array(STN::APP_NAME => "application name",
                     STN::APP_VERSION => "version",
                     STN::APP_COPYRIGHT => "copyright") as $setting => $title) {
        $val = DB::$V->reqString($args, $setting, 1, 101, sprintf("Invalid %s provided.", $title));
        if ($val != DB::g($setting)) {
          $changed = true;
          DB::s($setting, $val);
        }
      }

      $val = DB::$V->reqString($args, STN::TS_FROM_MAIL, 1, 1001, "No from address provided.");
      if ($val != DB::g(STN::TS_FROM_MAIL)) {
        $changed = true;
        DB::s(STN::TS_FROM_MAIL, $val);
      }

      $val = DB::$V->incString($args, STN::DIVERT_MAIL, 1, 101);
      if ($val != DB::g(STN::DIVERT_MAIL)) {
        $changed = true;
        DB::s(STN::DIVERT_MAIL, $val);
      }

      foreach (array(STN::SAILOR_API_URL,
                     STN::COACH_API_URL,
                     STN::SCHOOL_API_URL,
                     STN::HELP_HOME) as $setting) {
        $val = DB::$V->incRE($args, $setting, '_^https?://.{5,}$_', array(null));
        if ($val[0] != DB::g($setting)) {
          $changed = true;
          DB::s($setting, $val[0]);
        }
      }

      if (!$changed)
        throw new SoterException("No changes to save.");
      Session::pa(new PA("Saved settings."));
    }
  }
}
?>