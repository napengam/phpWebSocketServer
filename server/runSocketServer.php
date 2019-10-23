<?php

include '../include/certPath.inc.php';
include '../include/adressPort.inc.php';
include '../include/logToFile.inc.php';
include 'resource.php';
include 'logToFile.php';
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
include 'resourceWeb.php';
include 'resourcePHP.php';
/*
 * ***********************************************
 * create a logger
 * set directory for logfiles and 
 * log to console false
 * ***********************************************
 */
$logger = new logToFile($logDir,true);
if ($logger->error === '') {
    $logger->logOpen('webSockLog');
} else {
    $logger = '';
}
/*
 * *****************************************
 * create server 
 * *****************************************
 */
$server = new WebsocketServer($Address, $Port, $logger, $keyAndCertFile, $pathToCert);

/*
 * ***********************************************
 * instantiate backend 'applications'
 * ***********************************************
 */
$resWeb = new resourceWeb();
$resPHP = new resourcePHP();
/*
 * ***********************************************
 * register backend 'applications' with server
 * ***********************************************
 */
$server->registerResource('/web', $resWeb);
$server->registerResource('/php', $resPHP);
/*
 * ***********************************************
 * now start it to have the server handle
 * requests from clients
 * ***********************************************
 */

$server->Start();
?>
