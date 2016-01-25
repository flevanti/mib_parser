<?php
session_start();

require 'get_ryan_quotes.class.php';
require 'curl.class.php';
require 'settings.php';
require 'get_args.include.php';

$args = getArgs();


$days_offset = isset($args['days_offset']) ? $args['days_offset'] : 0;
$day_to_check = isset($args['days_to_check']) ? $args['days_to_check'] : NULL;

$obj = new get_ryan_quotes();
$obj->setDaysOffset($days_offset);
$obj->setDaysToCheck($day_to_check);
$obj->setDbConnectionInfo($db);
$obj->session_id = session_id();
$obj->getFares();
