<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Manage the regatta types and their ranks
 *
 * @author Dayan Paez
 * @created 2013-03-06
 */
class MailingListManagement extends AbstractAdminUserPane {

  public function __construct(Account $user) {
    parent::__construct("Mailing lists", $user);
    $this->page_url = 'lists';
  }

  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Mailing lists by regatta types"));
    $p->add(new XP(array(), "Scorers have the option of sending a summary e-mail once for each day of competition. This auto-generated message will be sent to the mailing lists associated with that regatta's type. Use the form below to specify which mailing lists to use for each regatta type."));
    $p->add(new XP(array(), "Please note that in all cases, the e-mail will be sent to the participating conferences; so there is no need to specify those below. Enter each e-mail address on a newline."));

    // Do not allow re ID of in-use regatta types either
    foreach (DB::getAll(DB::$ACTIVE_TYPE) as $type) {
      $p->add($f = $this->createForm());
      $list = $type->mail_lists;
      if ($list === null)
        $list = array();
      $f->add($fi = new FItem($type . ":", new XTextArea('lists', implode("\n", $list))));
      $fi->add(new XHiddenInput('type', $type->id));
      $fi->add(new XSubmitInput('set-lists', "Update"));
    }
  }

  public function process(Array $args) {
    if (isset($args['set-lists'])) {
      $type = DB::$V->reqID($args, 'type', DB::$ACTIVE_TYPE, "Invalid or missing type.");
      $lists = DB::$V->incString($args, 'lists', 1, 16000, null);
      if ($lists !== null)
        $lists = explode(" ", preg_replace('/[\s,]+/', ' ', $lists));
      $type->mail_lists = $lists;
      DB::set($type);
      Session::pa(new PA(sprintf("Updated mailing lists for regattas of type \"%s\".", $type)));
    }
  }
}
?>