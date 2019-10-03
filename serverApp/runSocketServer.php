<?php

/*
 * **************************
 * include address and port like:
 * $Address=[ssl:// | tcp://]server.at.com
 * $Port=number
 * **************************
 */
include '../include/certPath.inc.php';
include '../include/adressPort.inc.php';
include 'coreApp.php';
/*
 * ***********************************************
 * inlcude the core server
 * ***********************************************
 */
include "webSocketServer.php";
/*
 * **********************************************
 *  your backend application
 * **********************************************
 */

include 'appWeb.php';
include 'appPHP.php';

/*
 * *****************************************
 * start server 
 * *****************************************
 */


$server = new WebsocketServer($Address, $Port, $keyAndCertFile, $pathToCert);
$appWeb = new appWeb();
$appPHP = new appPHP();
$server->registerApp('/web', $appWeb);
$server->registerApp('/php', $appPHP);
$server->logToFile = false;
$server->Start();
?>
