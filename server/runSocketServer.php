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
 *  your backend applications
 * **********************************************
 */
include 'testAppWeb.php';
include 'testAppPHP.php';
/*
 * *****************************************
 * start server 
 * *****************************************
 */
$server = new WebsocketServer($Address, $Port, $keyAndCertFile, $pathToCert);
/*
 * ***********************************************
 * instantiate backend 'applications'
 * ***********************************************
 */
$appWeb = new appWeb();
$appPHP = new appPHP();
/*
 * ***********************************************
 * register backend 'applications' with server
 * ***********************************************
 */
$server->registerApp('/web', $appWeb);
$server->registerApp('/php', $appPHP);
/*
 * ***********************************************
 * now start it to have the server handle
 * requests from clients
 * ***********************************************
 */
$server->Start();
?>
