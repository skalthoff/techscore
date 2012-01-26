<?php
/**
 * Here's something! A non-processing page: meant only for searching
 * the database of sailors, because that could be useful, no?
 *
 * @author Dayan Paez
 * @created 2011-04-20
 */

require_once('../lib/conf.php');

//
// Is logged-in
//
if (!Session::has('user')) {
  Session::s('last_page', $_SERVER['REQUEST_URI']);

  // provide the login page
  Session::pa(new PA("Please login to proceed.", PA::I));
  $PAGE = new WelcomePage();
  $PAGE->printXML();
  exit;
}

// Validate input
if (!isset($_GET['q']) || strlen($_GET['q']) < 3) {
  Session::pa(new PA("Please provide a long enough query to search.", PA::I));
  WS::go('/');
}

$results = DB::searchSailors($_GET['q']);
require_once('xml5/XmlLib.php');
$P = new XDoc('SailorSearch', array('version'=>'1.0', 'count'=>count($results)));
foreach ($results as $result) {
  $school = DB::getSchool($result->school);
  $P->add(new XElem('Sailor', array('id'=>$result->id, 'icsa_id'=>$result->icsa_id),
		    array(new XElem('FirstName', array(), array(new XText($result->first_name))),
			  new XElem('LastName',  array(), array(new XText($result->last_name))),
			  new XElem('Year',      array(), array(new XText($result->year))),
			  new XElem('Gender',    array(), array(new XText($result->gender))),
			  new XElem('School', array('id'=>$school->id),
				    array(new XText($school->name))))));
}
$P->printXML();
?>