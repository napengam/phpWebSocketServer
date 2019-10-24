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
include 'resourceDefault.php';
include 'resourceWeb.php';
include 'resourcePHP.php';

function check_set($n, $v = '') {
    if (isset($_GET[$n])) {
        return ($_GET[$n]);
    }
    return($v);
}

/*
 * ***********************************************
 * check for parameters 
 * ***********************************************
 */

$console = false;
if ($argc > 1) {
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
    $logdir = check_set('ld', $logDir);
    $console = check_set('co', false);
}
/*
 * ***********************************************
 * create a logger
 * set directory for logfiles and 
 * log to console false
 * ***********************************************
 */
$logger = new logToFile($logDir, $console);
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
$resDefault = new resourceDefault();
$resWeb = new resourceWeb();
$resPHP = new resourcePHP();
/*
 * ***********************************************
 * register backend 'applications' with server
 * ***********************************************
 */
$server->registerResource('/', $resDefault);
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
