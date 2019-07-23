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

$talk = new socketTalk($Address, $Port);
$talk->talk(['opcode' => 'broadcast', 'message' => "$message 1"]);
$talk->talk(['opcode' => 'broadcast', 'message' => "$message 2"]);
$talk->talk(['opcode' => 'broadcast', 'message' => "$message 3"]);
$talk->talk(['opcode' => 'broadcast', 'message' => "$message 4"]);
$talk->talk(['opcode' => 'broadcast', 'message' => "$message 5"]);
$talk->talk(['opcode' => 'quit']);

$talk->silent();

 