<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

/**
 * Drop individual penalties
 *
 * @author Dayan Paez
 * @version 2010-01-25
 */
class DropPenaltyPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Drop penalty", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $penalties = array();
    $handicaps = array();
    $modifiers = array(); // map of finish ID => modifier

    $penlist = Penalty::getList();
    $bkdlist = Breakdown::getList();
    foreach ($this->REGATTA->getPenalizedFinishes() as $finish) {
      $penalty = $finish->getModifier();
      $modifiers[$finish->id] = $penalty;
      if (isset($penlist[$penalty->type]) &&
          ($this->REGATTA->scoring != Regatta::SCORING_TEAM || $penalty->type != Penalty::DNS))
        $penalties[] = $finish;
      elseif (isset($bkdlist[$penalty->type]))
        $handicaps[] = $finish;
    }

    // ------------------------------------------------------------
    // Existing penalties
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Penalties"));

    if (count($penalties) == 0) {
      $p->add(new XP(array(), "There are currently no penalties."));
    }
    else {
      $p->add($tab = new XQuickTable(array(), array("Race", "Team", "Type", "Comments", "Amount", "Displace?", "Action")));
      foreach ($penalties as $finish) {
        $modifier = $modifiers[$finish->id];
        $amount = $modifier->amount;
        if ($amount < 1)
          $amount = "FLEET + 1";
        $displace = "";
        if ($modifier->displace > 0)
          $displace = new XImg(WS::link('/inc/img/s.png'), "✓");
        $team = $finish->team;
        if ($this->REGATTA->scoring == Regatta::SCORING_TEAM)
          $team = $finish->race->division->getLevel() . ': ' . $team;
        $tab->addRow(array($finish->race,
                           $team,
                           $modifier->type,
                           $modifier->comments,
                           $amount,
                           $displace,
                           $form = $this->createForm()));

        $form->add(new XHiddenInput("r_finish", $finish->id));
        $form->add($sub = new XSubmitInput("p_remove", "Drop/Reinstate", array("class"=>"thin")));
      }
    }

    // ------------------------------------------------------------
    // Existing breakdowns
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Breakdowns"));

    if (count($handicaps) == 0) {
      $p->add(new XP(array(), "There are currently no breakdowns."));
    }
    else {
      $p->add($tab = new XQuickTable(array(), array("Race", "Team", "Type", "Comments", "Amount", "Displace", "Action")));
      foreach ($handicaps as $finish) {
        $modifier = $modifiers[$finish->id];
        $amount = $modifier->amount;
        if ($amount < 1)
          $amount = "Average in division";
        $displace = "";
        if ($modifier->displace > 0)
          $displace = new XImg(WS::link('/inc/img/s.png'), "✓");
        $team = $finish->team;
        if ($this->REGATTA->scoring == Regatta::SCORING_TEAM)
          $team = $finish->race->division->getLevel() . ': ' . $team;
        $tab->addRow(array($finish->race,
                           $team,
                           $modifier->type,
                           $modifier->comments,
                           $amount,
                           $displace,
                           $form = $this->createForm()));

        $form->add(new XHiddenInput("r_finish", $finish->id));
        $form->add($sub = new XSubmitInput("p_remove", "Drop/Reinstate",
                                           array("class"=>"thin")));
      }
    }
  }

  public function process(Array $args) {

    // ------------------------------------------------------------
    // Drop penalty/breakdown
    // ------------------------------------------------------------
    if (isset($args['p_remove'])) {

      $finish = DB::$V->reqID($args, 'r_finish', DB::$FINISH, "Invalid or missing finish provided.");
      if ($finish->race->regatta != $this->REGATTA ||
          $finish->getModifier() == null)
        throw new SoterException("Invalid finish provided.");
      $finish->setModifier();
      $this->REGATTA->commitFinishes(array($finish));
      $this->REGATTA->runScore($finish->race);
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);

      // Announce
      Session::pa(new PA(sprintf("Dropped penalty for %s in race %s.", $finish->team, $finish->race)));
    }
    return $args;
  }
}