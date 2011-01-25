<?php
/**
 * Explicitly request to score a regatta, passed by ID
 *
 * @author Dayan Paez
 * @version 2011-01-24
 * @package bin
 */

function usage() {
  global $argv;
  printf("usage: %s <regatta-id>\n", $argv[0]);
  exit(1);
}

if (count($argv) < 2)
  usage();

ini_set('include_path', '.:'.realpath(dirname(__FILE__).'/../lib'));
require_once('conf.php');
try {
  $reg = new Regatta($argv[1]);
  $reg->scorer->score($reg);
}
catch (Exception $e) {
  printf("Invalid regatta ID provided: %s\n\n", $argv[1]);
  usage();
}
?>