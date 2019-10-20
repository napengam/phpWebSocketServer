<?php

if ($argc > 1) {
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
}

require 'socketPhpClient.php';
include '../include/adressPort.inc.php';

$talk = new socketTalk($Address, $Port, '/php');
if (!isset($_GET['m'])) {
    $_GET['m'] = '';
}


$message = trim($_GET['m']);
if ($message == '') {
    //return;
    $message = 'hallo from PHP';
} else {
    $talk->talk(['opcode' => 'broadcast', 'message' => "$message"]);
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

$talk->talk(['opcode' => 'broadcast', 'message' => "$message 1"]);
$talk->talk(['opcode' => 'broadcast', 'message' => "$message 2"]);
$talk->talk(['opcode' => 'broadcast', 'message' => "$message 3"]);
$talk->talk(['opcode' => 'broadcast', 'message' => "$message 4"]);
$talk->talk(['opcode' => 'broadcast', 'message' => "$message 5"]);
$talk->talk(['opcode' => 'broadcast', 'message' => "$longString 6~6~6~6"]);


$talk->silent();

