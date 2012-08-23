<?php
/*
 * Uses the local variables in lib/conf.local.php to update the
 * different system files that comprise the local installation of
 * TechScore.
 *
 * This script should be run whenever conf.local.php to generate the
 * apache.conf file, the crontab file, and (eventually) the database
 * updates.
 *
 * @author Dayan Paez
 * @version 2012-04-11
 * @package bin
 */

require_once(dirname(dirname(__FILE__)).'/lib/conf.php');

function usage($mes = null) {
  if ($mes !== null)
    echo "$mes\n\n";
  echo "usage: php Make.php <apache.conf|crontab|getprop PROP>

Use 'getprop' to retrieve a constant from Conf class.\n";
  exit(1);
}

$targets = array('apache.conf', 'crontab', 'getprop');
$args = array();
if (!isset($argv) || !is_array($argv) || count($argv) < 1)
  usage("missing argument list");

$base = array_shift($argv);
while (count($argv) > 0) {
  $arg = array_shift($argv);
  if (!in_array($arg, $targets))
    usage("invalid argument: $arg");
  $args[$arg] = 1;
  // Handle properties
  if ($arg == 'getprop') {
    if (count($argv) == 0)
      usage("getprop: missing property to get");
    $prop = array_shift($argv);
    if (!isset(Conf::$$prop))
      usage("getprop: invalid property $prop");
    printf("%s\n", Conf::$$prop);
  }
}

if (count($args) == 0)
  usage("no arguments provided");

$pwd = dirname(dirname(__FILE__));
// ------------------------------------------------------------
// apache.conf
// ------------------------------------------------------------
if (isset($args['apache.conf'])) {
  $template = $pwd . '/src/apache.conf.default';
  if (($path = realpath($template)) === false)
    usage("template not found: $template");
  $str = file_get_contents($path);
  $str = str_replace('{DIRECTORY}', $pwd, $str);
  $str = str_replace('{HOSTNAME}', Conf::$HOME, $str);
  $str = str_replace('{PUBLIC_HOSTNAME}', Conf::$PUB_HOME, $str);
  $str = str_replace('{HTTP_LOGROOT}', Conf::$LOG_ROOT, $str);
  $str = str_replace('{HTTP_CERTPATH}', Conf::$HTTP_CERTPATH, $str);
  $str = str_replace('{HTTP_CERTKEYPATH}', Conf::$HTTP_CERTKEYPATH, $str);
  $str = str_replace('{HTTP_CERTCHAINPATH}', Conf::$HTTP_CERTCHAINPATH, $str);

  $output = $pwd . '/src/apache.conf';
  if (file_put_contents($output, $str) === false)
    usage("unable to write file: $output");
}

// ------------------------------------------------------------
// crontab
// ------------------------------------------------------------
if (isset($args['crontab'])) {
  $template = $pwd . '/src/crontab.default';
  if (($path = realpath($template)) === false)
    usage("template not found: $template");
  $str = file_get_contents($path);
  $str = str_replace('{DIRECTORY}', $pwd, $str);
  $str = str_replace('{DB_DB}', Conf::$SQL_DB, $str);
  $str = str_replace('{CRON_MAILTO}', Conf::$ADMIN_MAIL, $str);
  $str = str_replace('{CRON_FREQ}', Conf::$CRON_FREQ, $str);

  $output = $pwd . '/src/crontab';
  if (file_put_contents($output, $str) === false)
    usage("unable to write file: $output");
}
?>