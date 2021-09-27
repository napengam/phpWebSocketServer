<?php

require_once __DIR__ . '/../include/adressPort.inc.php';
require_once __DIR__ . '/../phpClient/websocketPhp.php';

/*
************************************************
* identfy as receiver 
************************************************
*/

$talk = new websocketPhp($Address . '/php', 'receiver');
/*
************************************************
* now wait for messages 
************************************************
*/
while (true) {
    $msg = $talk->readSocket(); // read will wait for data 
    echo "$msg<br>";
    ob_flush();
    flush();   
}