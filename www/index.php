<?php
/**
 * Gateway to the program TechScore. Manage all session information
 * and direct traffic.
 *
 * @author Dayan Paez
 * @version 2.0
 * @created 2009-10-16
 */

require_once('conf.php');

// ------------------------------------------------------------
// HEAD method used to determine status
// ------------------------------------------------------------
if (Conf::$METHOD == 'HEAD') {
  if (Conf::$USER === null)
    header('HTTP/1.1 403 Permission denied');
  exit(0);
}

// ------------------------------------------------------------
// Verify method
// ------------------------------------------------------------
if (!in_array(Conf::$METHOD, array('POST', 'GET')))
  Conf::do405();

// ------------------------------------------------------------
// Construct the URI
// ------------------------------------------------------------
$URI = WS::unlink($_SERVER['REQUEST_URI'], true);
$URI_TOKENS = array();
foreach (explode('/', $URI) as $arg) {
  if (strlen($arg) > 0)
    $URI_TOKENS[] = $arg;
}
if (count($URI_TOKENS) == 0)
  $URI_TOKENS = array('home');

// ------------------------------------------------------------
// Not logged-in?
// ------------------------------------------------------------
if (Conf::$USER === null) {
  // Registration?
  switch ($URI_TOKENS[0]) {
  case 'register':
    if (DB::g(STN::ALLOW_REGISTER) === null)
      WS::go('/');

    // When following mail verification, simulate POST
    if (count($URI_TOKENS) > 1) {
      Conf::$METHOD = 'POST';
      $_POST['acc'] = $URI_TOKENS[1];
      $_POST['csrf_token'] = Session::getCsrfToken();
    }
    require_once('users/RegisterPane.php');
    $PAGE = new RegisterPane();
    break;

  case 'password-recover':
    require_once('users/PasswordRecoveryPane.php');
    $PAGE = new PasswordRecoveryPane();
    break;

  case 'login':
    require_once('users/LoginPage.php');
    $PAGE = new LoginPage();
    break;

  default:
    if (Conf::$METHOD == 'POST')
      WS::go($URI);

    Session::s('last_page', $_SERVER['REQUEST_URI']);
    require_once('users/LoginPage.php');
    $PAGE = new LoginPage();
  }
  if (Conf::$METHOD == 'POST') {
    Session::s('POST', $PAGE->processPOST($_POST));
    WS::goBack('/');
  }
  $PAGE->getHTML($_GET);
  exit;
}

// ------------------------------------------------------------
// User registered at this point
// ------------------------------------------------------------
if ($URI_TOKENS[0] == 'license') {
  require_once('users/EULAPane.php');
  $PAGE = new EULAPane(Conf::$USER);
  if (Conf::$METHOD == 'POST') {
    $PAGE->processPOST($_POST);
    WS::go('/');
  }
  $PAGE->getHTML($_GET);
  exit;
}
if ($URI_TOKENS[0] == 'logout') {
  $_GET['dir'] = 'out';
  require_once('users/LoginPage.php');
  $PAGE = new LoginPage();
  $PAGE->getHTML(array('dir'=>'out'));
  exit;
}
DB::requireActive(Conf::$USER);

// ------------------------------------------------------------
// Process regatta requests
// ------------------------------------------------------------
if (in_array($URI_TOKENS[0], array('score', 'view', 'download'))) {
  try {
    if (!Conf::$USER->canAny(array(Permission::EDIT_REGATTA, Permission::PARTICIPATE_IN_REGATTA)))
      throw new PermissionException("No permission to edit regattas.");

    $BASE = array_shift($URI_TOKENS);
    if (count($URI_TOKENS) == 0) {
      throw new PermissionException("Missing regatta.");
    }
    $REG = DB::getRegatta(array_shift($URI_TOKENS));
    if ($REG === null) {
      throw new PermissionException("No such regatta.");
    }

    $is_participant = false;
    if (!Conf::$USER->hasJurisdiction($REG)) {
      if ($REG->private === null && Conf::$USER->isParticipantIn($REG)) {
        $is_participant = true;
      }
      else {
        throw new PermissionException("You do not have permission to edit that regatta.");
      }
    }

    // User and regatta authorized, delegate to AbstractPane
    $PAGE = null;
    if ($BASE == 'score') {
      require_once('tscore/AbstractPane.php');
      $PAGE = AbstractPane::getPane($URI_TOKENS, Conf::$USER, $REG);
      if ($PAGE === null) {
        $mes = sprintf("Invalid page requested (%s)", implode('/', $URI_TOKENS));
        Session::pa(new PA($mes, PA::I));
        WS::go('/score/'.$REG->id);
      }
      if (!$PAGE->isActive()) {
        $title = $PAGE->getTitle();
        Session::pa(new PA("\"$title\" is not available.", PA::I));
        WS::go('/score/'.$REG->id);
      }
      // Participant?
      if ($is_participant) {
        $PAGE->setParticipantUIMode($is_participant);
      }

      // process, if so requested
      if (Conf::$METHOD == 'POST') {
        require_once('public/UpdateManager.php');
        Session::s('POST', $PAGE->processPOST($_POST));
        WS::goBack('/');
      }
    }

    // 'view' and 'download' requires GET method only
    if (Conf::$METHOD != 'GET')
      Conf::do405("Only GET method supported for dialogs and downloads.");

    if ($BASE == 'view') {
      require_once('tscore/AbstractDialog.php');
      $PAGE = AbstractDialog::getDialog($URI_TOKENS, Conf::$USER, $REG);
      if ($PAGE === null) {
        $mes = sprintf("Invalid page requested (%s)", implode('/', $URI_TOKENS));
        Session::pa(new PA($mes, PA::I));
        WS::go('/view/'.$REG->id);
      }
    }

    if ($BASE == 'download') {
      $st = $REG->start_time;
      $nn = $REG->nick;
      if (count($REG->getTeams()) == 0 || count($REG->getDivisions()) == 0) {
        Session::pa(new PA("First create teams and divisions before downloading.", PA::I));
        WS::go('/score/'.$REG->id);
      }

      if (count($URI_TOKENS) == 0) {
        Session::pa(new PA("Nothing to download. Please try again.", PA::I));
        WS::go('/score/'.$REG->id);
      }
      switch ($URI_TOKENS[0]) {

        // --------------- REGATTA ---------------//
        /*
          case "":
          case "regatta":
          $name = sprintf("%s-%s.tsr", $st->format("Y"), $nn);
          header("Content-type: text/xml");
          header(sprintf('Content-disposition: attachment; filename="%s"', $name));
          echo RegattaIO::toXML($REG);
          break;
        */

        // --------------- RP FORMS --------------//
      case 'rp':
      case 'rpform':
      case 'rps':
        $name = sprintf('%s-%s-rp', $st->format('Y'), $nn);
        $rp = $REG->getRpManager();
        if ($rp->isFormRecent())
          $data = $rp->getForm();
        else {
          $form = DB::getRpFormWriter($REG);
          if ($form === null) {
            Session::pa(new PA("Downloadable PDF forms are not available for this regatta type.", PA::I));
            WS::go('/score/'.$REG->id);
          }

          $sock = DB::g(STN::PDFLATEX_SOCKET);
          if ($sock === null)
            $data = $form->makePdf($REG);
          else
            $data = $form->socketPdf($REG, $sock);

          if ($data === null) {
            Session::pa(new PA("Downloadable PDF forms are not available for this regatta type.", PA::I));
            WS::go('/score/'.$REG->id);
          }

          $rp->setForm($data);
        }

        header('Content-type: application/pdf');
        header(sprintf('Content-Disposition: attachment; filename="%s.pdf"', $name));
        echo $data;
        exit;

        // --------------- RP Templates ---------------//
      case 'rp-template':
      case 'rp-empty':
        $form = DB::getRpFormWriter($REG);
        if ($form === null || ($name = $form->getPdfName()) === null) {
          Session::pa(new PA("Empty PDF forms are not available for this regatta type.", PA::I));
          WS::go('/score/'.$REG->id);
        }

        header('Content-type: application/pdf');
        header(sprintf('Content-Disposition: attachment; filename="%s"', basename($name)));
        echo file_get_contents($name);
        exit;

        // --------------- default ---------------//
      default:
        $mes = sprintf("Invalid download requested (%s)", $_GET['d']);
        Session::pa(new PA("Invalid download requested.", PA::I));
        WS::go('/score/'.$REG->id);
      }
    }

    $args = $_GET;
    $post = Session::g('POST');
    if (is_array($post))
      $args = array_merge($post, $args);
    $PAGE->getHTML($args);
    exit;
  }
  catch (PermissionException $e) {
    Session::pa(new PA($e->getMessage(), PA::E));
    if ($e->regatta !== null)
      WS::go('/score/' . $e->regatta->id);
    WS::go('/');
  }
}

// ------------------------------------------------------------
// School burgee stash
// ------------------------------------------------------------
if ($URI_TOKENS[0] == 'inc') {
  if (count($URI_TOKENS) != 4 ||
      $URI_TOKENS[1] != 'img' ||
      $URI_TOKENS[2] != 'schools') {
    http_response_code(404);
    exit;
  }
  $name = basename($URI_TOKENS[3], '.png');
  $id = $name;
  $prop = 'burgee';
  if (substr($name, -3) == '-40') {
    $id = substr($name, 0, strlen($name) - 3);
    $prop = 'burgee_small';
  }
  if (($school = DB::getSchool($id)) === null ||
      $school->$prop === null) {
    http_response_code(404);
    exit;
  }

  // Cache headings
  header("Cache-Control: public");
  header("Pragma: public");
  header("Content-Type: image/png");
  header("Expires: Sun, 21 Jul 2030 14:08:53 -0400");
  header(sprintf("Last-Modified: %s", $school->$prop->last_updated->format('r')));
  echo base64_decode($school->$prop->filedata);
  exit;
}

// ------------------------------------------------------------
// Regular, non-scoring panes
// ------------------------------------------------------------
require_once('users/AbstractUserPane.php');
try {
  $PAGE = AbstractUserPane::getPane($URI_TOKENS, Conf::$USER);
  if (Conf::$METHOD == 'POST') {
    Session::s('POST', $PAGE->processPOST($_POST));
    WS::goBack('/');
  }
  $post = Session::g('POST');
  $args = array_merge((is_array($post)) ? $post : array(), $_GET);
  $PAGE->getHTML($args);
}
catch (PaneException $e) {
  Session::pa(new PA($e->getMessage(), PA::E));
  WS::go('/');  
}
catch (PermissionException $e) {
  Session::pa(new PA($e->getMessage(), PA::E));
  WS::goBack('/');
}
?>
