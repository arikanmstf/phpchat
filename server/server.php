#!/usr/bin/env php 
<?php
require '_inc/api.php';
ini_set('max_execution_time', 0);

$host = '192.168.2.20'; //host
$port = '9000'; //port


$host = isset($argv[1]) ? $argv[1] : 'localhost';
$port = isset($argv[2]) ? $argv[2] : '9000';

$api = new ChatApp($host,$port);
$api->run();
