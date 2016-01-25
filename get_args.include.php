<?php


function getArgs() {
  global $argc, $argv;
  $args = array();

  if (PHP_SAPI == 'cli') {
    if ($argc > 1) {
      foreach ($argv as $k => $v) {
        if ($k == 0) { //that's the script name
          continue;
        }
        $arg_key = strstr($v, "=", TRUE);
        if (empty($arg_key)) {
          $arg_key = $v;
          $arg_value = NULL;
        }
        else {
          $arg_value = ltrim(strstr($v, "="), "=");
        }
        $args[$arg_key] = $arg_value;
      } //end foreach
    }
  }
  else { //web browser
    $args = empty($_GET) ? array() : $_GET;
  }

  return $args;

}
