<?php

if ($argc > 1) {
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
}

require 'websocketPhp.php';
include '../include/adressPort.inc.php';
$talk = new websocketPhp($Address . '/php');
if (!isset($_GET['m'])) {
    $_GET['m'] = '';
}


$message = trim($_GET['m']);
if ($message == '') {
    //return;
    $message = 'hallo from PHP';
} else {
    $talk->broadcast($message);
    $talk->silent();
    exit;
}
$longString = '';
for ($i = 0; $i < 9 * 1024; $i++) {
    $longString .= 'P';
}

/*
 * ***********************************************
 * test if messages apear in same order as send
 * no message is lost and very long message is buffered
 * ***********************************************
 */

$talk->broadcast("$message 1");
$talk->broadcast("$message 2");
$talk->broadcast("$message 3");
$talk->broadcast("$message 4");
$talk->broadcast("$message 5");
$talk->broadcast("äüöÄÜÖß$longString 6~6~6~6 ÄÜÖßäüö");
$talk->broadcast("$message 6");

$talk->silent();

