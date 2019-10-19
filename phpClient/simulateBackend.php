<?php

require 'socketPhpClient.php';
include '../include/adressPort.inc.php';
/*
 * ***********************************************
 * get parameters from client
 * ***********************************************
 */
$json = file_get_contents('php://input');
$payload = (object) json_decode($json, true);
/*
 * ***********************************************
 * connect to teh server
 * ***********************************************
 */
$talk = new socketTalk($Address, $Port, '/php');
/*
 * ***********************************************
 * send feedback to client
 * ***********************************************
 */

$talk->talk(['opcode' => 'feedback', 'uuid' => $payload->uuid, 'message' => "doing some work sleep(3)"]);
sleep(3);
$talk->talk(['opcode' => 'feedback', 'uuid' => $payload->uuid, 'message' => "doing other work  sleep(2)"]);
sleep(2);
for ($i = 0; $i < 1000000; $i++) {
    if ($i % 1000 == 0) {
        $talk->talk(['opcode' => 'feedback', 'uuid' => $payload->uuid, 'message' => "loop $i"]);
    }
}
$talk->talk(['opcode' => 'feedback', 'uuid' => $payload->uuid, 'message' => "done"]);
/*
 * ***********************************************
 * end of AJAX call
 * ***********************************************
 */
echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);

