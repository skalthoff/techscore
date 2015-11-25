<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-09-18
 * @package scripts
 */

use \scripts\AbstractScript;

/**
 * Creates the front page: Includes a brief welcoming message,
 * displayed alongside the list of regattas sailing now, if any. (The
 * welcome message takes up the entire width otherwise).
 *
 * Underneath that, includes a list of upcoming events, in ascending
 * chronological order.
 *
 */
class UpdateFront extends AbstractScript {

  private function getPage() {
    require_once('xml5/TPublicFrontPage.php');
    $page = new TPublicFrontPage();

    // Add welcome message
    $page->addSection($div = new XDiv(array('id'=>'message-container')));
    $div->add($sub = new XDiv(array('id'=>'welcome'), array($this->h1("Welcome"))));

    $entry = DB::get(DB::T(DB::TEXT_ENTRY), Text_Entry::WELCOME);
    if ($entry !== null && $entry->html !== null)
      $sub->add(new XRawText($entry->html));

    // Menu
    $page->addMenu(new XA('/', "Home"));
    $page->addMenu(new XA('/schools/', "Schools"));
    $page->addMenu(new XA('/seasons/', "Seasons"));

    // Get current season's coming regattas

    $success = false;
    $seasons = Season::getActive();
    if (count($seasons) == 0) {
      if (($lnk = $page->getOrgLink()) !== null)
        $page->addMenu($lnk);

      // Wow! There is NO information to report!
      $page->addSection(new XPort("Nothing to show!", array(new XP(array(), "We are sorry, but there are no regattas in the system! Please come back later. Happy sailing!"))));
      return $page;
    }

    $page->addMenu(new XA($seasons[0]->getURL(), $seasons[0]->fullString()));
    if (($lnk = $page->getOrgLink()) !== null)
      $page->addMenu($lnk);

    // ------------------------------------------------------------
    // Are there any regattas in progress? Such regattas must be
    // happening now according to date, and have a status not equal to
    // 'SCHEDULED' (which usually indicates that a regatta is not yet
    // ready, and might possibly never be scored).
    $start = new DateTime();
    $start->setTime(23, 59, 59);
    $end = new DateTime();
    $end->setTime(0, 0, 0);
    $potential = DB::getAll(DB::T(DB::PUBLIC_REGATTA),
                            new DBBool(array(new DBCond('start_time', $start, DBCond::LE),
                                             new DBCond('end_date', $end, DBCond::GE),
                                             new DBCond('dt_status', Regatta::STAT_SCHEDULED, DBCond::NE))));
    if (count($potential) > 0) {
      $div->add($this->createInProgressPort($potential, "In progress"));
    }
    else {
      // ------------------------------------------------------------
      // Are there any regattas that ended no more than 2 days ago?
      // Let's show those, too
      $start->sub(new DateInterval('P2DT0H'));
      $end->sub(new DateInterval('P2DT0H'));
      $potential = DB::getAll(DB::T(DB::PUBLIC_REGATTA),
                              new DBBool(array(new DBCond('start_time', $start, DBCond::LE),
                                               new DBCond('end_date', $end, DBCond::GE),
                                               new DBCond('dt_status', Regatta::STAT_SCHEDULED, DBCond::NE))));
      if (count($potential) > 0) {
        $div->add($this->createInProgressPort($potential, "Recent regattas"));
      }
      elseif (DB::g(STN::FLICKR_ID) !== null) {
        $div->add(new XDiv(array('id'=>'flickwrap'),
                           array(new XElem('iframe', array('src'=>sprintf('//www.flickr.com/slideShow/index.gne?group_id=&user_id=%s', urlencode(DB::g(STN::FLICKR_ID))),
                                                           'width'=>500,
                                                           'height'=>500),
                                           array(new XText(""))))));
      }
    }

    // ------------------------------------------------------------
    // Fill list of coming soon regattas
    $now = new DateTime('tomorrow');
    $now->setTime(0, 0);
    DB::T(DB::PUBLIC_REGATTA)->db_set_order(array('start_time'=>true));
    $regs = array();
    foreach (DB::getAll(DB::T(DB::PUBLIC_REGATTA), new DBCond('start_time', $now, DBCond::GE)) as $reg) {
      if ($reg->dt_status !== null && $reg->dt_status != Regatta::STAT_SCHEDULED)
        $regs[] = $reg;
    }
    DB::T(DB::PUBLIC_REGATTA)->db_set_order();
    if (count($regs) > 0) {
      $page->addSection($p = new XPort("Upcoming schedule"));
      $p->add($tab = new XQuickTable(array('class'=>'coming-regattas'),
                                     array("Name",
                                           "Host",
                                           "Type",
                                           "Scoring",
                                           "Start time")));
      foreach ($regs as $reg) {
        $tab->addRow(array(new XA($reg->getURL(), $reg->name),
                           $reg->getHostVenue(),
                           $reg->type,
                           $reg->getDataScoring(),
                           $reg->start_time->format('m/d/Y @ H:i')));
      }
    }

    // ------------------------------------------------------------
    // Add links to all seasons
    $num = 0;
    $ul = new XUl(array('id'=>'other-seasons'));
    $seasons = Season::getActive();
    foreach ($seasons as $s) {
      if (count($s->getRegattas()) > 0) {
        $num++;
        $ul->add(new XLi(new XA('/'.$s.'/', $s->fullString())));
      }
    }
    if ($num > 0)
      $page->addSection(new XDiv(array('id'=>'submenu-wrapper'),
                                       array(new XH3("Other seasons", array('class'=>'nav')), $ul)));
    return $page;
  }

  /**
   * Creates an "#in-progress" port with the list of regattas given
   *
   * @param Array $regs the list of regattas
   * @param String $title the title to use for the port
   * @return XDiv the div created
   */
  private function createInProgressPort($regs, $title) {
    if (!is_array($regs)) {
      $in_prog = array();
      foreach ($regs as $reg) {
        $in_prog[] = $reg;
      }
    }
    usort($in_prog, 'Regatta::cmpTypes');
    $div = new XDiv(array('id'=>'in-progress'),
                    array($this->h1($title),
                          $tab = new XQuickTable(array('class'=>'season-summary'),
                                                 array("Name",
                                                       "Type",
                                                       "Scoring",
                                                       "Status",
                                                       "Leading"))));
    foreach ($in_prog as $i => $reg) {
      $row = array(new XA($reg->getURL(), $reg->name), $reg->type, $reg->getDataScoring());
      $tms = $reg->getRankedTeams();
      if ($reg->dt_status == Regatta::STAT_READY || count($tms) == 0) {
        $row[] = new XTD(array('colspan'=>2), new XEm("No scores yet"));
      }
      else {
        $row[] = new XStrong(ucwords($reg->dt_status));
        $row[] = $tms[0]->school->drawSmallBurgee((string)$tms[0]);
      }
      $tab->addRow($row, array('class'=>'row'.($i % 2)));
    }
    return $div;
  }

  /**
   * Creates a fancy, three-part, h1 heading
   *
   * @param String $heading the title of the heading (arg to XSpan)
   * @return XH1
   */
  private function h1($heading) {
    $h1 = new XH1("");
    $h1->add(new XSpan("", array('class'=>'left-fill')));
    $h1->add(new XSpan($heading));
    $h1->add(new XSpan("", array('class'=>'right-fill')));
    return $h1;
  }

  /**
   * Creates the new page summary in the public domain
   *
   */
  public function run() {
    self::write('/index.html', $this->getPage());
  }
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new UpdateFront();
  $opts = $P->getOpts($argv);
  if (count($opts) > 0)
    throw new TSScriptException("Invalid argument(s).");
  $P->run();
}
?>
