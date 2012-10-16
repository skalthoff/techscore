<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('users/AbstractUserPane.php');

/**
 * Searches for a given sailor in the database, returning the record
 *
 * @author Dayan Paez
 * @created 2012-10-12
 */
class SearchSailor extends AbstractUserPane {
  public function __construct(Account $user) {
    parent::__construct("Fetch sailors");
    $this->page_url = 'search';
  }

  /**
   * Overrides parent method to return XML document, instead of
   * TScorePage
   *
   */
  public function getHTML(Array $args) {
    $P = new XDoc('SailorSearch', array('version'=>'1.0'));

    // Validate input
    try {
      $query = DB::$V->reqString($args, 'q', "Please provide a query (GET=q).");
      if (strlen($query) < 3)
        throw new SoterException("Please provide a long enough query (3 or more characters.");
      $results = DB::searchSailors($query, true);
      $P->set('count', count($results));
      foreach ($results as $result) {
        $P->add(new XElem('Sailor', array('id'=>$result->id, 'icsa_id'=>$result->icsa_id),
                          array(new XElem('FirstName', array(), array(new XText($result->first_name))),
                                new XElem('LastName',  array(), array(new XText($result->last_name))),
                                new XElem('Year',      array(), array(new XText($result->year))),
                                new XElem('Gender',    array(), array(new XText($result->gender))),
                                new XElem('School',    array('id' => $result->school->id),
                                          array(new XText($result->school->name))))));
      }
      $P->printXML();

    }
    catch (SoterException $e) {
      header('HTTP/1.1 400 Bad request');
      $P->set('count', -1);
      $P->add(new XElem('Error', array(), array(new XText("Please provide a long enough query to search."))));
      $P->printXML();
      exit;
    }
   }

  protected function fillHTML(Array $args) {}
  public function process(Array $args) {
    throw new SoterException("Searching does not accept POST requests.");
  }
}
?>