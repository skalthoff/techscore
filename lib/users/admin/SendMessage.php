<?php
/**
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @created 2011-11-05
 */

require_once('conf.php');

/**
 * Pane for administrators to send messages to one or more users,
 * based on certain criteria
 *
 */
class SendMessage extends AbstractAdminUserPane {
  public function __construct(User $user) {
    parent::__construct("Send message", $user);
  }

  /**
   * To send messages, administrators can either choose all accounts, 
   * all accounts from a conference, all accounts from individual
   * schools, or all accounts, period.
   *
   */
  public function fillHTML(Array $args) {
    if (isset($args['recipients'])) {
      $this->fillMessage($args);
      return;
    }

    $this->PAGE->addContent($p = new Port("1. Choose recipients"));
    $p->addChild(new Para("You may send a message to as many individuals as you'd like at a time. First, select the recipients using this port. Once you have added all recipients, use the form below to send the message."));

    $p->addChild($f = new Form("/send-message-edit"));
    $f->addChild($fi = new FItem(sprintf("All %s users:", NAME), new FHidden('all-recipients', 1)));
    $fi->addChild(new FSubmit('choose-recipients', "Write message >"));
    $fi->addChild(new FSpan("Broadcast general message to all users. Use sparingly.", array('class'=>'message')));

    // conference
    $p->addChild($f = new Form("/send-message-edit"));
    $f->addChild($fi = new FItem("All users in conference:", $sel = new FSelect('conferences[]')));
    $fi->addChild(new Text(" "));
    $fi->addChild(new FSubmit('choose-recipients', "Write message >"));
    $opts = array();
    foreach (Preferences::getConferences() as $conf)
      $opts[$conf->id] = $conf;
    $sel->addOptions($opts);
    $sel->addAttr('multiple', 'multiple');
    $sel->addAttr('size', 7);

    // roles
    $p->addChild($f = new Form("/send-message-edit"));
    $f->addChild($fi = new FItem("All users with role:", $sel = new FSelect('roles[]')));
    $fi->addChild(new Text(" "));
    $fi->addChild(new FSubmit('choose-recipients', "Write message >"));
    $sel->addOptions(array('coach'=>"Coaches",
			   'staff'=>"Staff",
			   'student'=>"Students"));
    $sel->addAttr('multiple', 'multiple');
    $sel->addAttr('size', 3);
  }

  private function fillMessage(Array $args) {
    $this->PAGE->addContent($p = new Port("Instructions"));
    $p->addChild($f = new Form('/send-message-edit'));
    $f->addChild(new FSubmit('reset-recipients', "<< Restart"));
    $p->addChild(new Para("When filling out the form, you may use the keywords in the table below to customize each message."));
    $p->addChild($tab = new Table());
    $tab->addHeader(new Row(array(Cell::th("Keyword"), Cell::th("Description"), Cell::th("Example"))));
    $tab->addRow(new Row(array(new Cell("{FULL_NAME}"), new Cell("Full name of user"), new Cell($this->USER->getName()))));
    $tab->addRow(new Row(array(new Cell("{SCHOOL}"), new Cell("User's ICSA school"), new Cell($this->USER->get(User::SCHOOL)))));
    $tab->addAttr('style', 'margin:0 auto 2em;');
    $f = new Form('/send-message-edit');
    switch ($args['recipients']) {
    case 'all':
      $this->PAGE->addContent($p = new Port("2. Send message to all users"));
      $p->addChild($f);
      $f->addChild(new FItem("Recipients:", new FSpan("All users", array('class'=>'strong'))));
      break;

      // conference
    case 'conferences':
      $this->PAGE->addContent($p = new Port("2. Send message to users from conference(s)"));
      $p->addChild($f);
      $f->addChild(new FItem("Recipients:", new FSpan(implode(", ", $args['conferences']), array('class'=>'strong'))));
      break;
    }
    
    $f->addChild($fi = new FItem("Subject:", new FText('subject', "")));
    $fi->addChild(new FSpan("Less than 100 characters", array('class'=>'message')));

    $f->addChild(new FItem("Message body:", new FTextarea('content', "", array('rows'=>16, 'cols'=>75))));
    $f->addChild($fi = new FItem("Copy me:", new FCheckbox('copy-me', 1)));
    $fi->addChild(new FSpan("Send me a copy of message, whether or not I would otherwise receive one.", array('class'=>'message')));
    $f->addChild(new FSubmit('send-message', "Send message now"));
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Restart
    // ------------------------------------------------------------
    if (isset($args['reset-recipients'])) {
      return array();
    }

    // ------------------------------------------------------------
    // Choose recipients, will ya?
    // ------------------------------------------------------------
    if (isset($args['choose-recipients'])) {
      if (isset($args['all-recipients']) && $args['all-recipients'] > 0) {
	return array('recipients'=>'all');
      }
      if (isset($args['conferences'])) {
	if (!is_array($args['conferences']) || count($args['conferences']) == 0) {
	  $this->announce(new Announcement("No conferences provided. Please try again.", Announcement::WARNING));
	  return array();
	}
	$confs = array();
	foreach ($args['conferences'] as $conf) {
	  $c = Preferences::getConference($conf);
	  if ($c !== null)
	    $confs[$c->id] = $c;
	}
	if (count($confs) == 0) {
	  $this->announce(new Announcement("No conferences provided. Please try again.", Announcement::WARNING));
	  return array();
	}
	return array('recipients'=>'conferences', 'conferences'=>$confs);
      }
    }

    // ------------------------------------------------------------
    // Send message
    // ------------------------------------------------------------
    if (isset($args['send-message'])) {
      if (!isset($_SESSION['POST']['recipients'])) {
	$this->announce(new Announcement("No recipients found.", Announcement::ERROR));
	return array();
      }
      // require non-empty subject and content
      if (!isset($args['subject']) || ($sub = trim($args['subject'])) == "") {
	$this->announce(new Announcement("Subject must not be empty.", Announcement::ERROR));
	return $_SESSION;
      }
      if (!isset($args['content']) || ($cnt = trim($args['content'])) == "") {
	$this->announce(new Announcement("Message body must not be empty.", Announcement::ERROR));
	return $_SESSION;
      }

      $sent_to_me = false;
      if ($_SESSION['POST']['recipients'] == 'all') {
	foreach (Preferences::getConferences() as $conf) {
	  foreach (Preferences::getUsersFromConference($conf) as $acc) {
	    $this->send($acc, $sub, $cnt);
	    if ($acc->id == $this->USER->username())
	      $sent_to_me = true;
	  }
	}
	$this->announce(new Announcement("Successfully sent message to all recipients."));
      }
      if ($_SESSION['POST']['recipients'] == 'conferences') {
	foreach ($_SESSION['POST']['conferences'] as $conf) {
	  foreach (Preferences::getUsersFromConference($conf) as $acc) {
	    $this->send($acc, $sub, $cnt);
	    if ($acc->id == $this->USER->username())
	      $sent_to_me = true;
	  }
	}
	$this->announce(new Announcement(sprintf("Successfully sent message to %s users.", implode(", ", $_SESSION['POST']['conferences']))));
      }

      // send me a copy?
      if (isset($args['copy-me']) && !$sent_to_me)
	$this->send($this->USER->asAccount(), "COPY OF: ".$sub, $cnt);
      return array();
    }
  }

  private function send(Account $to, $subject, $content) {
    Preferences::queueMessage($to, $this->keywordReplace($to, $subject), $this->keywordReplace($to, $content), true);
  }
  private function keywordReplace(Account $to, $mes) {
    $mes = str_replace('{FULL_NAME}', $to->getName(), $mes);
    $mes = str_replace('{SCHOOL}',    $to->school, $mes);
    return $mes;
  }
}

?>