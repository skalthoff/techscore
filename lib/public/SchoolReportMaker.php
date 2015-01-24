<?php
/*
 * This file is part of Techscore
 *
 * @author Dayan Paez
 * @version 2015-01-20
 */

require_once('xml5/TPublicPage.php');

/**
 * Public profile page generator for a given school
 *
 * @author Dayan Paez
 * @created 2015-01-24
 */
class SchoolReportMaker {
  private $school;
  private $season;

  private $mainPage;

  /**
   * Creates a new report for given school and season
   *
   * @param School $school the school in question
   * @param Season $season the season
   */
  public function __construct(School $school, Season $season) {
    $this->school = $school;
    $this->season = $season;
  }

  /**
   * Generate landing page for a school
   *
   * @return XPage the page
   */
  public function getMainPage() {
    $this->fillMainPage();
    return $this->mainPage;
  }

  private function fillMainPage() {
    if ($this->mainPage !== null)
      return;

    $school = $this->school;
    $season = $this->season;

    $this->mainPage = new TPublicPage(sprintf("%s | %s", $school, $season->fullString()));
    $this->mainPage->body->set('class', 'school-page');
    $this->mainPage->setDescription(sprintf("Summary of activity for %s during the %s season.", $school, $season->fullString()));
    $this->mainPage->addMetaKeyword($school->name);
    if ($school->name != $school->nick_name)
      $this->mainPage->addMetaKeyword($school->nick_name);
    $this->mainPage->addMetaKeyword($season->getSeason());
    $this->mainPage->addMetaKeyword($season->getYear());
    $this->mainPage->addSocialPlugins(true);

    $url = sprintf('http://%s%s', Conf::$PUB_HOME, $school->getURL());
    $og = array('type'=>'website', 'url'=>$url);
    if ($school->hasBurgee()) {
      $imgurl = sprintf('http://%s/inc/img/schools/%s.png', Conf::$PUB_HOME, $school->id);
      $this->mainPage->setTwitterImage($imgurl);
      $og['image'] = $imgurl;
    }

    $this->mainPage->setFacebookLike($url);
    $this->mainPage->setOpenGraphProperties($og);

    $this->mainPage->body->set('itemscope', 'itemscope');
    $this->mainPage->body->set('itemtype', 'http://schema.org/CollegeOrUniversity');

    // SETUP navigation
    $this->mainPage->addMenu(new XA('/', "Home"));
    $this->mainPage->addMenu(new XA('/schools/', "Schools"));
    $this->mainPage->addMenu(new XA('/seasons/', "Seasons"));
    $this->mainPage->addMenu(new XA($school->getURL(), $school->nick_name));
    if (($link = $this->getBlogLink()) !== null)
      $this->mainPage->addMenu(new XA($link, "Blog", array('itemprop'=>'url')));
    if (($link = $this->mainPage->getOrgTeamsLink()) !== null)
      $this->mainPage->addMenu($link);

    if (($img = $school->drawBurgee(null, array('itemprop'=>'image'))) !== null)
      $this->mainPage->addSection(new XP(array('class'=>'burgee'), $img));

    // current season
    $today = new DateTime();
    $today->setTime(0, 0);
    $tomorrow = new DateTime('tomorrow');
    $tomorrow->setTime(0, 0);

    $regs = $season->getParticipation($school);
    $total = count($regs);
    $current = array(); // regattas happening NOW
    $past = array();    // past regattas from the current season
    $coming = array();  // upcoming schedule

    foreach ($regs as $reg) {
      if ($reg->dt_status === null || $reg->dt_status == Regatta::STAT_SCHEDULED) {
        continue;
      }
      if ($reg->start_time < $tomorrow && $reg->end_date >= $today) {
        $current[] = $reg;
      }
      if ($reg->end_date < $today) {
        $past[] = $reg;
      }
      if ($reg->start_time >= $tomorrow) {
        $coming[] = $reg;
      }
    }

    // ------------------------------------------------------------
    // SCHOOL season summary
    $conference_link = $school->conference;
    if (DB::g(STN::PUBLISH_CONFERENCE_SUMMARY) !== null) {
      $conference_link = new XA($school->conference->url, $conference_link);
    }
    $table = array(DB::g(STN::CONFERENCE_TITLE) => $conference_link,
                   "Number of Regattas" => $total);
    $this->mainPage->setHeader($school, $table, array('itemprop'=>'name'));

    // ------------------------------------------------------------
    // SCHOOL sailing now
    if (count($current) > 0) {
      usort($current, 'Regatta::cmpTypes');
      $this->mainPage->addSection($p = new XPort("Sailing now", array(), array('id'=>'sailing')));
      $p->add($tab = new XQuickTable(array('class'=>'participation-table'),
                                     array("Name", "Host", "Type", "Scoring", "Last race", "Place(s)")));
      foreach ($current as $row => $reg) {
        // borrowed from UpdateSeason
        $status = null;
        switch ($reg->dt_status) {
        case Regatta::STAT_READY:
          $status = new XEm("No scores yet");
          break;

        default:
          $status = new XStrong(ucwords($reg->dt_status));
        }

        $link = new XA($reg->getURL(), $reg->name);
        $tab->addRow(array($link,
                           $reg->getHostVenue(),
                           $reg->type,
                           $reg->getDataScoring(),
                           $status,
                           $this->getPlaces($reg)),
                     array('class' => 'row' . ($row % 2)));
      }
    }
    // ------------------------------------------------------------
    // SCHOOL coming soon: ONLY if there is no current ones
    elseif (count($coming) > 0) {
      usort($coming, 'Regatta::cmpTypes');
      $this->mainPage->addSection($p = new XPort("Coming soon"));
      $p->add($tab = new XQuickTable(array('class'=>'coming-regattas'),
                                     array("Name",
                                           "Host",
                                           "Type",
                                           "Scoring",
                                           "Start time")));
      foreach ($coming as $reg) {
        $tab->addRow(array(new XA($reg->getURL(), $reg->name),
                           $reg->getHostVenue(),
                           $reg->type,
                           $reg->getDataScoring(),
                           $reg->start_time->format('m/d/Y @ H:i')));
      }
    }

    // ------------------------------------------------------------
    // SCHOOL past regattas
    if (count($past) > 0) {
      $season_link = new XA($season->getURL(), $season->fullString());
      $this->mainPage->addSection($p = new XPort(array("Season history for ", $season_link)));
      $p->set('id', 'history');

      $p->add($tab = new XQuickTable(array('class'=>'participation-table'),
                                     array("Name", "Host", "Type", "Scoring", "Date", "Status", "Place(s)")));

      foreach ($past as $row => $reg) {
        $link = new XA(sprintf('/%s/%s/', $season, $reg->nick),
                       new XSpan($reg->name, array('itemprop'=>'name')),
                       array('itemprop'=>'url'));
        $tab->addRow(array($link,
                           $reg->getHostVenue(),
                           $reg->type,
                           $reg->getDataScoring(),
                           new XTime($reg->start_time, 'M d', array('itemprop'=>'startDate')),
                           ($reg->finalized === null) ? "Pending" : new XStrong("Official"),
                           $this->getPlaces($reg)),
                     array('class' => sprintf('row' . ($row % 2)),
                           'itemprop'=>'event',
                           'itemscope'=>'itemscope',
                           'itemtype'=>'http://schema.org/SportsEvent'));
      }
    }

    // ------------------------------------------------------------
    // Add links to all seasons
    $ul = new XUl(array('id'=>'other-seasons'));
    $num = 0;
    $root = $school->getURL();
    foreach (DB::getAll(DB::T(DB::SEASON)) as $s) {
      $regs = $s->getParticipation($school);
      if (count($regs) > 0) {
        $num++;
        $ul->add(new XLi(new XA($root . $s->shortString() . '/', $s->fullString())));
      }
    }
    if ($num > 0)
      $this->mainPage->addSection(new XDiv(array('id'=>'submenu-wrapper'),
                                           array(new XH3("Other seasons", array('class'=>'nav')),
                                                 $ul)));
  }

  /**
   * Helper method provides a string representation of school's
   * current placement
   *
   */
  private function getPlaces(Regatta $reg) {
    $places = array();
    $teams = $reg->getRankedTeams($this->school);
    foreach ($teams as $team) {
      $places[] = $team->dt_rank;
    }
    if (count($teams) == 0)
      return "";
    return sprintf('%s/%d', implode(',', $places), count($reg->getTeams()));
  }

  private function getBlogLink() {
    return null;
    // Turned off until we can figure out a consistent way of doing
    $link_fmt = 'http://collegesailing.info/blog/teams/%s';
    return sprintf($link_fmt, str_replace(' ', '-', strtolower($this->school->name)));
  }
}
?>