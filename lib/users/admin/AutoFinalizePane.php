<?php
namespace users\admin;

use \AbstractAdminUserPane;
use \Account;
use \DB;
use \PermissionException;
use \SoterException;
use \STN;
use \Session;

use \FItem;
use \FReqItem;
use \XPort;
use \XNumberInput;
use \XSubmitP;

use \ui\StnCheckbox;

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Manage the settings for the auto-finalize feature, which mut be
 * turned on.
 *
 * @author Dayan Paez
 * @version 2015-10-29
 */
class AutoFinalizePane extends AbstractAdminUserPane {

  const SUBMIT_ENABLE_STATUS = 'enable-status';
  const SUBMIT_SETTINGS = 'update-settings';

  public function __construct(Account $user) {
    parent::__construct("Auto-finalize settings", $user);
    if (DB::g(STN::ALLOW_AUTO_FINALIZE) === null) {
      throw new PermissionException("This feature is not enabled.");
    }
  }

  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Feature status"));
    $p->add($form = $this->createForm());
    $form->add(new FItem("Enabled:", new StnCheckbox(STN::AUTO_FINALIZE_ENABLED, "Auto finalize regattas according to user-specified settings.")));
    $form->add(new XSubmitP(self::SUBMIT_ENABLE_STATUS, "Update"));

    if (DB::g(STN::AUTO_FINALIZE_ENABLED) !== null) {
      $this->PAGE->addContent($p = new XPort("Settings"));
      $p->add($form = $this->createForm());

      $form->add(new FReqItem("Auto-finalize after (days):", new XNumberInput(STN::AUTO_FINALIZE_AFTER_N_DAYS, DB::g(STN::AUTO_FINALIZE_AFTER_N_DAYS), 1)));
      $form->add(new FItem("Auto assess MRP:", new StnCheckbox(STN::AUTO_ASSESS_MRP_ON_AUTO_FINALIZE, "Add Missing RP penalty to teams when auto-finalizing.")));

      $form->add(new XSubmitP(self::SUBMIT_SETTINGS, "Update"));
    }
  }

  public function process(Array $args) {
    if (array_key_exists(self::SUBMIT_ENABLE_STATUS, $args)) {
      DB::s(
        STN::AUTO_FINALIZE_ENABLED,
        DB::$V->incInt($args, STN::AUTO_FINALIZE_ENABLED, 1, 2, null)
      );
      Session::info("Feature status updated.");
    }

    if (array_key_exists(self::SUBMIT_SETTINGS, $args)) {
      $changed = false;
      $val = DB::$V->incInt($args, STN::AUTO_ASSESS_MRP_ON_AUTO_FINALIZE, 1, 2, null);
      if ($val != DB::g(STN::AUTO_ASSESS_MRP_ON_AUTO_FINALIZE)) {
        $changed = true;
        DB::s(STN::AUTO_ASSESS_MRP_ON_AUTO_FINALIZE, $val);
      }

      $val = DB::$V->reqInt($args, STN::AUTO_FINALIZE_AFTER_N_DAYS, 1, 1001, "Invalid number of days specified.");
      if ($val != DB::g(STN::AUTO_FINALIZE_AFTER_N_DAYS)) {
        $changed = true;
        DB::s(STN::AUTO_FINALIZE_AFTER_N_DAYS, $val);
      }

      if (!$changed) {
        throw new SoterException("Nothing changed.");
      }
      Session::info("Settings updated.");
    }
  }
}