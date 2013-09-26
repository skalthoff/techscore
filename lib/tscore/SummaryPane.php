<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once('conf.php');

/**
 * Changes the daily summaries for the regatta
 *
 * @author Dayan Paez
 * @version 2010-03-24
 */
class SummaryPane extends AbstractPane {

  /**
   * Creates a new editing pane
   *
   */
  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Summaries", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->head->add(new LinkCSS('/inc/css/sum.css'));

    $duration = $this->REGATTA->getDuration();
    $f = $this->createForm(XForm::GET);
    $f->add($prog = new XP(array('id'=>'progressdiv')));
    if ($duration > 1)
      $this->PAGE->addContent($f);

    $this->PAGE->addContent($xp = new XPort("About the daily summaries"));
    $xp->add($p = new XP(array(), "A text summary is required for each day of competition for all public regattas."));
    if ($this->REGATTA->private == null) {
      $p->add(" The summaries will be printed as part of the ");
      $txt = "regatta report";
      if ($this->REGATTA->dt_status != Regatta::STAT_SCHEDULED)
        $txt = new XA(sprintf('http://%s%s', Conf::$PUB_HOME, $this->REGATTA->getUrl()), $txt);
      $p->add($txt);
      $p->add(". In addition, the summaries will be used in the daily e-mail message report, if the checkbox is selected below. Note that e-mails may only be sent once per day.");
    }
    $xp->add(new XP(array(), "Tips for writing summaries:"));
    $xp->add(new XUl(array(),
                                    array(new XLi("Write directly in the form below, or copy and paste from Notepad or similar plain-text editor. Some Office Productivity Suites add invalid encoding characters that may not render properly."),
                                          new XLi("Leave an empty line to create a new paragraph. Short paragraphs are easier to read."),
                                          new XLi("Good summaries consist of a few sentences (at least 3) that describe the event, race conditions, and acknowledge the staff at the event."),
                                          new XLi("Do not include a reference to the day in the summary, as this is automatically included in all reports and is thus redundant."),
                                          new XLi("Do not include hyperlinks to the scores site, as these can change and should be generated only by the program."))));

    $this->PAGE->addContent($p = new XPort("Daily summary"));
    $p->add($form = $this->createForm());

    // Which day's summary?
    $s = clone($this->REGATTA->start_time);
    $s->setTime(0, 0);
    $e = clone($this->REGATTA->end_date);
    $e->setTime(23, 59, 59);
    $day = DB::$V->incDate($args, 'day', $s, $e, null);
    if ($day === null) {
      $now = clone(DB::$NOW);
      $now->setTime(0, 0);
      $diff = $now->diff($s);
      if ($now <= $s)
        $day = $s;
      elseif ($diff->days >= $duration)
        $day = $e;
      else {
        $day = clone($s);
        $day->add(new DateInterval(sprintf('P%dDT0H', $diff->days)));
      }
    }
    $summ = $this->REGATTA->getSummary($day);
    $form->add(new XHiddenInput('day', $day->format('Y-m-d')));
    $form->add(new XH4($day->format('l, F j')));
    $form->add(new XP(array(), new XTextArea('summary', $summ, array('rows'=>30, 'id'=>'summary-textarea'))));

    if ($summ === null || $summ->mail_sent === null) {
      $form->add($fi = new FItem("Send e-mail:", new XCheckboxInput('email', 1, array('id'=>'chk-mail'))));
      $fi->add(new XLabel('chk-mail', "Click to send e-mail to appropriate mailing lists with regatta details."));
    }
    $form->add(new XSubmitP('set_comment', 'Save summary'));

    $day = $day->format('Y-m-d');
    for ($i = 0; $i < $duration; $i++) {
      if ($s->format('Y-m-d') == $day)
        $prog->add(new XSpan($s->format('l, F j')));
      else {
        $prog->add($sub = new XSubmitInput('day', $s->format('l, F j')));
      }
      $s->add(new DateInterval('P1DT0H'));
    }
  }

  /**
   * Processes changes to daily summaries
   *
   */
  public function process(Array $args) {
    if (isset($args['set_comment'])) {
      $s = clone($this->REGATTA->start_time);
      $s->setTime(0, 0);
      $e = clone($this->REGATTA->end_date);
      $e->setTime(23, 59, 59);
      $day = DB::$V->reqDate($args, 'day', $s, $e, "No date provided for summary.");
      $summ = $this->REGATTA->getSummary($day);
      if ($summ === null)
        $summ = new Daily_Summary();
      $summ->summary = DB::$V->incString($args, 'summary', 1, 16000, null);
      $this->REGATTA->setSummary($day, $summ);
      Session::pa(new PA(sprintf("Updated summary for %s.", $day->format('l, F j'))));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SUMMARY);

      // Send mail?
      if ($summ->summary !== null && $summ->mail_sent === null && $this->REGATTA->private === null &&
          DB::$V->incInt($args, 'email', 1, 2, null) !== null) {

        $recips = array();
        foreach ($this->REGATTA->type->mail_lists as $target)
          $recips[$target] = $target;
        // Add participant conferences
        foreach ($this->REGATTA->getTeams() as $team) {
          $recips[strtoupper($team->school->conference->id)] = sprintf('%s@lists.collegesailing.org', strtolower($team->school->conference->id));
        }

        $this->sendMessage($recips, $summ);
        $summ->mail_sent = 1;
        DB::set($summ);
        Session::pa(new PA(sprintf("Sent e-mail message to the %s list%s.",
                                   implode(", ", array_keys($recips)),
                                   (count($recips) > 1) ? "s" : "")));
      }
    }
    return $args;
  }

  protected function sendMessage(Array $recips, Daily_Summary $summ) {
    $W = 70;
    $body = "";
    $body .= $this->centerInLine($this->REGATTA->name, $W) . "\r\n";
    $body .= $this->centerInLine($this->REGATTA->type, $W) . "\r\n";
    $body .= $this->centerInLine($this->REGATTA->getDataScoring(), $W) . "\r\n";

    $hosts = array();
    foreach ($this->REGATTA->getHosts() as $host)
      $hosts[] = $host->nick_name;
    $boats = array();
    foreach ($this->REGATTA->getBoats() as $boat)
      $boats[] = $boat;
    $body .= $this->centerInLine(sprintf("%s in %s", implode(", ", $hosts), implode(", ", $boats)), $W) . "\r\n";
    $url = sprintf('http://%s%s', Conf::$PUB_HOME, $this->REGATTA->getUrl());
    $body .= $this->centerInLine($url, $W) . "\r\n";
    $body .= "\r\n";

    $str = $summ->summary_date->format('l, F j');
    $body .= $str . "\r\n";
    for ($i = 0; $i < mb_strlen($str); $i++)
      $body .= "-";
    $body .= "\r\n";
    $body .= "\r\n";
    $paras = explode("\r\n\r\n", $summ);
    foreach ($paras as $para)
      $body .= wordwrap($para, $W, " \r\n") . "\r\n\r\n";

    $body .= "\r\n";
    $body .= sprintf("Top %d\r\n", min(5, count($this->REGATTA->getTeams())));
    $body .= "-----\r\n";
    $body .= "\r\n";
    $body .= wordwrap(sprintf("Visit %s for full results.", $url), $W, " \r\n");
    $body .= "\r\n\r\n";

    if ($this->REGATTA->hasFinishes()) {
      $body .= $this->getResultsTable($W) . "\r\n";
    }

    $body .= "\r\n";
    $body .= "-- \r\n";
    $body .= wordwrap(sprintf("This message sent by %s on behalf of %s.", Conf::$NAME, Conf::$USER), $W, " \r\n");

    foreach ($recips as $recip)
      DB::mail($recip, $this->REGATTA->name, $body);
  }

  protected function centerInLine($str, $W) {
    $pad = ceil(($W + mb_strlen($str)) / 2);
    return sprintf('%' . $pad . 's', $str);
  }

  protected function getResultsTable($W = 70) {
    $table = array();
    $colwidths = array();
    $divisions = $this->REGATTA->getDivisions();

    function updateColwidths(Array $values, &$colwidths) {
      foreach ($values as $i => $value) {
        if (!isset($colwidths[$i]))
          $colwidths[$i] = 0;
        if (mb_strlen($value) > $colwidths[$i])
          $colwidths[$i] = mb_strlen($value);
      }
    }
    $ranks = $this->REGATTA->getRankedTeams();
    if ($this->REGATTA->scoring != Regatta::SCORING_TEAM) {
      // Make table
      foreach ($ranks as $r => $rank) {
        if ($r >= 5)
          break;

        $row = array(($r + 1),
                     $rank->school->nick_name,
                     $rank->name);
        $tot = 0;
        foreach ($divisions as $div) {
          $div_rank = $rank->getRank($div);
          if ($div_rank === null) {
            $row[] = " "; // to account for header and leaderstar
            $row[] = "";
          }
          else {
            $row[] = $div_rank->score;
            $row[] = (string)$div_rank->penalty;
            $tot += $div_rank->score;
          }
        }
        $row[] = $tot;
        updateColwidths($row, $colwidths);
        $table[] = $row;
      }

      // Last cell is "TOT"
      if ($colwidths[count($colwidths) - 1] < 3)
        $colwidths[count($colwidths) - 1] = 3;

      // Alignment
      $alignment = array("", "", "-");
      foreach ($divisions as $div) {
        $alignment[] = "";
        $alignment[] = "-";
      }
      $alignment[] = "";

      // Column separator
      $sep = "    ";

      // Maximum row width
      $basewidth = 0;
      foreach ($colwidths as $width)
        $basewidth += $width;
      do {
        $sep = substr($sep, 0, -1);
        $rowwidth = $basewidth + strlen($sep) * (count($colwidths) - 1);
      } while ($rowwidth > $W && strlen($sep) > 1);

      // Line prefix
      $prefix = floor(($W - $rowwidth) / 2);

      // Generate table string, centered on $W
      $str = "";

      // Headers
      $str .= sprintf('%' . $prefix . 's', "");
      $str .= sprintf('%' . $colwidths[0] . 's', "#") . $sep;
      $span = ($colwidths[1] + $colwidths[2] + $sep);
      $pad = floor(($span + 4) / 2);
      $str .= sprintf('%' . $pad . 's', "") . "Team";
      $str .= sprintf('%' . ($span - $pad - 1) . 's', "") . $sep;
      $i = 3;
      foreach ($divisions as $j => $div) {
        $str .= sprintf('%' . $colwidths[$i + (2 * $j)] . 's', $div) . $sep;
        $str .= sprintf('%' . $colwidths[$i + (2 * $j) + 1] . 's',
                        $colwidths[$i + (2 * $j) + 1] > 0 ? "P" : "") . $sep;
      }
      $str .= sprintf('%' . $colwidths[count($colwidths) - 1] . 's', "TOT") . "\r\n";

      // ----------
      $str .= sprintf('%' . $prefix . 's', " ");
      for ($j = 0; $j < $rowwidth; $j++)
        $str .= "=";
      $str .= "\r\n";

      foreach ($table as $i => $row) {
        $str .= sprintf('%' . $prefix . 's', " ");
        foreach ($row as $j => $value) {
          if ($j > 0)
            $str .= $sep;

          $fmt = '%' . $alignment[$j] . $colwidths[$j] . 's';
          $str .= sprintf($fmt, $value);
        }
        $str .= "\r\n";
      }
      // ----------
      $str .= sprintf('%' . $prefix . 's', " ");
      for ($j = 0; $j < $rowwidth; $j++)
        $str .= "-";
      $str .= "\r\n";
      return $str;
    }
    else {
      // ------------------------------------------------------------
      // Team
      // ------------------------------------------------------------
      foreach ($ranks as $r => $rank) {
        if ($r >= 5)
          break;

        $row = array($rank->dt_rank,
                     $rank->school->nick_name,
                     $rank->name,
                     (int)$rank->dt_wins,
                     "-",
                     (int)$rank->dt_losses);
        updateColwidths($row, $colwidths);
        $table[] = $row;
      }

      // Alignment
      $alignment = array("", "", "-", "", "", "-");

      // Column separator
      $sep = "    ";

      // Maximum row width
      $basewidth = 0;
      foreach ($colwidths as $width)
        $basewidth += $width;
      do {
        $sep = substr($sep, 0, -1);
        $rowwidth = $basewidth + strlen($sep) * (count($colwidths) - 1);
      } while ($rowwidth > $W && strlen($sep) > 1);

      // Line prefix
      $prefix = floor(($W - $rowwidth) / 2);

      $str = "";

      // Headers
      $str .= sprintf('%' . $prefix . 's', "");
      $str .= sprintf('%' . $colwidths[0] . 's', "#") . $sep;
      $span = ($colwidths[1] + $colwidths[2] + $sep);
      $pad = floor(($span + 4) / 2);
      $str .= sprintf('%' . $pad . 's', "") . "Team";
      $str .= sprintf('%' . ($span - $pad - 1) . 's', "") . $sep;
      $str .= sprintf('%' . $colwidths[3] . 's', "W") . $sep;
      $str .= sprintf('%' . $colwidths[4] . 's', "-") . $sep;
      $str .= sprintf('%' . $colwidths[5] . 's', "L") . "\r\n";

      // ----------
      $str .= sprintf('%' . $prefix . 's', " ");
      for ($j = 0; $j < $rowwidth; $j++)
        $str .= "=";
      $str .= "\r\n";

      foreach ($table as $i => $row) {
        $str .= sprintf('%' . $prefix . 's', " ");
        foreach ($row as $j => $value) {
          if ($j > 0)
            $str .= $sep;

          $fmt = '%' . $alignment[$j] . $colwidths[$j] . 's';
          $str .= sprintf($fmt, $value);
        }
        $str .= "\r\n";
      }
      // ----------
      $str .= sprintf('%' . $prefix . 's', " ");
      for ($j = 0; $j < $rowwidth; $j++)
        $str .= "-";
      $str .= "\r\n";
      return $str;
    }
  }
}
?>
