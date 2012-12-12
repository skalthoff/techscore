<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */
require_once('tscore/EnterFinishPane.php');

/**
 * Enter finishes for team racing regattas
 *
 * @author Dayan Paez
 * @created 2012-12-11 
 */
class EnterTeamFinishPane extends EnterFinishPane {

  protected function fillHTML(Array $args) {
    // Chosen round
    $rounds = array();
    foreach ($this->REGATTA->getRounds() as $i => $r) {
      $rounds[$r->id] = $r;
      if ($i == 0)
        $round = $r;
    }

    // Chosen race, by number
    $num = DB::$V->incInt($args, 'race', 1, 1001, null);
    if ($num !== null) {
      $race = $this->REGATTA->getRace(Division::A(), $num);
      if ($race === null) {
        Session::pa(new PA("Invalid race chosen.", PA::E));
        $this->redirect();
      }
      $round = $race->round;
    }
    else {
      $round = DB::$V->incID($args, 'round', DB::$ROUND, $round);
      if (!isset($rounds[$round->id])) {
        Session::pa(new PA("Invalid round chosen.", PA::E));
        $this->redirect();
      }
      // Choose first race
      $races = $this->REGATTA->getRacesInRound($round);
      $race = $races[0];
    }

    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/finish.js'));
    // ------------------------------------------------------------
    // Choose race (duplicate of parent)
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Choose race by number"));
    $p->add($form = $this->createForm(XForm::GET));
    $form->set("id", "race_form");

    $form->add(new FItem("Race:", 
                         new XTextInput('race',
                                        $race,
                                        array("size"=>"4",
                                              "maxlength"=>"3",
                                              "id"=>"chosen_race",
                                              "class"=>"narrow"))));
    // No rotation yet
    $form->add(new XSubmitP("choose_race", "Change race"));

    // ------------------------------------------------------------
    // Choose round
    // ------------------------------------------------------------
    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/tr-finish-ui.js')));
    $this->PAGE->addContent($p = new XPort("Choose race by round"));
    $p->add($form = $this->createForm(XForm::GET));
    $form->set('id', 'round_form');
    $form->add(new FItem("Round:", $sel = XSelect::fromArray('round', $rounds, $round->id)));
    $sel->set('onchange', 'submit(this)');
    $form->add(new XSubmitAccessible("change_team", "Change"));

    // ------------------------------------------------------------
    // Choose race: provide grid
    // ------------------------------------------------------------
    require_once('tscore/ScoresGridDialog.php');
    $D = new ScoresGridDialog($this->REGATTA);
    $p->add($D->getRoundTable($round, true));

    // ------------------------------------------------------------
    // Enter finishes
    // ------------------------------------------------------------
    $this->fillFinishesPort($race);
  }
}
?>