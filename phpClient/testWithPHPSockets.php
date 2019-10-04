<?php

if ($argc > 1) {
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
}

require 'socketPhpClient.php';
include '../include/adressPort.inc.php';


$message = trim($_GET['m']);
if ($message == '') {
    //return;
    $message = 'hallo from PHP';
}
$secure = false;
if (isset($_GET['SSL'])) {
    $secure = true;
}
$longString = '';
for ($i = 0; $i < 100; $i++) {
    $longString .= '012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789';
}
$talk = new socketTalk($Address, $Port, '/php');

$talk->talk(['opcode' => 'broadcast', 'message' => "$message 1"]);
$talk->talk(['opcode' => 'broadcast', 'message' => "$message 2"]);
$talk->talk(['opcode' => 'broadcast', 'message' => "$message 3"]);
$talk->talk(['opcode' => 'broadcast', 'message' => "$message 4"]);
$talk->talk(['opcode' => 'broadcast', 'message' => "$message 5"]);
$talk->talk(['opcode' => 'broadcast', 'message' => "$longString 6~6~6~6"]);


$talk->silent();

