<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Edit the messages used throughout the public/private site
 *
 * @author Dayan Paez
 * @created 2013-03-12
 */
class TextManagement extends AbstractAdminUserPane {

  private $sections;

  /**
   * Creates a new text-management pane
   *
   * @param Account $user the user
   */
  public function __construct(Account $user) {
    parent::__construct("Edit text", $user);
    $this->sections = Text_Entry::getSections();
  }

  public function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Specific section
    // ------------------------------------------------------------
    if (isset($args['r'])) {
      try {
        $section = DB::$V->reqKey($args, 'r', $this->sections, "Invalid text section to edit.");
        $this->fillText($args, $section);
        return;
      }
      catch (SoterExceptions $e) {
        Session::pa(new PA($e->getMessage(), PA::E));
      }
    }

    // ------------------------------------------------------------
    // List of sections to edit
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Text entries"));
    $p->add($tab = new XQuickTable(array('class' => 'text-entries'),
                                   array("Name", "Description", "Current Value")));

    $i = 0;
    foreach ($this->sections as $section => $name) {
      $entry = DB::get(DB::$TEXT_ENTRY, $section);
      $display = new XEm("No entry exists.");
      if ($entry !== null)
        $display = new XDiv(array('class'=>'entry-preview'), array(new XRawText($entry->html)));
      
      $tab->addRow(
        array(
          new XA($this->link(array('r' => $section)), $name),
          $this->getExplanation($section),
          $display
        ),
        array('class' => 'row' . ($i++ % 2))
      );
    }
  }

  private function fillText(Array $args, $section) {
    $this->PAGE->head->add(new XLinkCSS('text/css', WS::link('/inc/css/preview.css'), 'screen', 'stylesheet'));
    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/DPEditor.js')));
    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/DPEditorUI.js')));
    $this->PAGE->head->add(new XScript('text/javascript', null,
                                       'window.onload = function(evt) { new DPEditor("content").uiInit();};'));

    $this->PAGE->addContent($p = new XPort("Edit " . $section));
    $p->add(new XP(array(), $this->getExplanation($section)));

    $entry = DB::get(DB::$TEXT_ENTRY, $section);
    if ($entry === null)
      $entry = new Text_Entry();

    $p->add($f = $this->createForm());
    $f->add(new XDiv(array(), array(new XTextArea('content', $entry->plain,
                                                  array('id'=>'content', 'cols'=>'80', 'rows'=>'10')))));
    $f->add($xp = new XSubmitP('set-text', "Save changes"));
    $xp->add(new XHiddenInput('section', $section));
    $xp->add(new XA($this->link(), "Cancel"));
  }

  public function process(Array $args) {
    $section = DB::$V->reqKey($args, 'section', $this->sections, "Invalid or missing text section to edit.");
    $entry = DB::get(DB::$TEXT_ENTRY, $section);
    if ($entry === null) {
      $entry = new Text_Entry();
      $entry->id = $section;
    }

    $input = DB::$V->incRaw($args, 'content', 1, 10000);
    if ($input == $entry->plain) {
      Session::pa(new PA("Nothing changed.", PA::I));
      return;
    }

    require_once('xml5/TSEditor.php');
    $DPE = new TSEditor();
    $entry->plain = $input;
    if ($input === null) {
      $entry->html = null;
    }
    else {
      $html = new XDiv(array(), $DPE->parse($input));
      $entry->html = $html->toXML();
    }
    DB::set($entry);

    // Notify appropriate updates (requires seasons)
    $seasons = DB::getAll(DB::$SEASON);
    if (count($seasons) > 0) {
      require_once('public/UpdateManager.php');
      switch ($section) {
      case Text_Entry::WELCOME:
        UpdateManager::queueSeason($seasons[0], UpdateSeasonRequest::ACTIVITY_FRONT);
        break;

      case Text_Entry::GENERAL_404:
        UpdateManager::queueSeason($seasons[0], UpdateSeasonRequest::ACTIVITY_404);
        break;

      case Text_Entry::SCHOOL_404:
        UpdateManager::queueSeason($seasons[0], UpdateSeasonRequest::ACTIVITY_SCHOOL_404);
        break;
      }
    }

    Session::pa(new PA(sprintf("Updated text section for \"%s\".",  $this->sections[$section])));
  }

  private function getExplanation($section) {
    switch ($section) {
    case Text_Entry::ANNOUNCEMENTS:
      return "Announcements are shown on the scoring login page. They provide a quick way to convey general information to the scorers and future registrants.";

    case Text_Entry::WELCOME:
      return "This message is shown in the \"Welcome\" box of the public home page.";

    case Text_Entry::GENERAL_404:
      return "This is the body of the 404 page shown with invalid URLs.";

    case Text_Entry::SCHOOL_404:
      return "This is the body of the 404 page shown with invalid URLs within the schools section.";

    case Text_Entry::EULA:
      return "The End-User License Agreement that is shown to new users.";

    case Text_Entry::REGISTER_MESSAGE:
      return "This is an optional message to be provided in the new user registration pane.";
    default:
      return "";
    }
  }
}
?>
