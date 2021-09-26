<?php

require_once __DIR__ . '/../include/adressPort.inc.php';
require_once __DIR__ . '/../phpClient/websocketPhp.php';

$talk = new websocketPhp($Address . '/php');

/*
 * ***********************************************
 * send messages to client identifyed as 'receiver'
 * ***********************************************
 */

$talk->feedback("Hello 1 from sender", 'receiver');
$talk->feedback("Hello 22 from sender", 'receiver');
$talk->feedback("Hello 333 from sender", 'receiver');
