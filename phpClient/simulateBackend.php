<?php

require 'websocketPhp.php';
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
 * connect to the server
 * ***********************************************
 */
$talk = new websocketPhp($Address, $Port, '/php', $payload->uuid);
/*
 * ***********************************************
 * send feedback to client
 * ***********************************************
 */

$talk->feedback("doing some work sleep(1)");
sleep(1); // work
$talk->feedback("very importand work  sleep(2)");
sleep(2); // work
for ($i = 0; $i < 1000000; $i++) {
    if ($i % 1000 == 0) {
        $talk->feedback("loop $i");
    }
}
$talk->feedback("done");
/*
 * ***********************************************
 * end of AJAX call
 * ***********************************************
 */
echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);

