<?php


require 'get_ryan_quotes.class.php';
require 'curl.class.php';
require 'settings.php';


$obj = new get_ryan_quotes();
$obj->db_conn_info = $db;
$obj->getFares();
