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
 * ***********************************************
 * instantiate backend 'applications'
 * ***********************************************
 */
$appWeb = new appWeb();
$appPHP = new appPHP();
/*
 * *****************************************
 * start server 
 * *****************************************
 */
$server = new WebsocketServer($Address, $Port, $keyAndCertFile, $pathToCert);
/*
 * ***********************************************
 * register backend 'applications' with server
 * ***********************************************
 */
$server->registerApp('/web', $appWeb);
$server->registerApp('/php', $appPHP);
/*
 * ***********************************************
 * now start it
 * ***********************************************
 */
$server->Start();
?>
