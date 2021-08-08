<?php

/*
 * ***********************************************
 * the runtime
 * ***********************************************
 */
include __DIR__ . '/../include/certPath.inc.php';
include __DIR__ . '/../include/adressPort.inc.php';
include __DIR__ . '/../include/logToFile.inc.php';
include __DIR__ . '/../include/errorHandler.php';
include __DIR__ . '/logToFile.php';
/*
 * ***********************************************
 * inlcude the core server
 * ***********************************************
 */
include __DIR__ . "/getOptions.php";
include __DIR__ . "/webSocketServer.php";
/*
 * **********************************************
 *  your backend applications
 * **********************************************
 */
include __DIR__ . '/resource.php';
include __DIR__ . '/resourceDefault.php';
include __DIR__ . '/resourceWeb.php';
include __DIR__ . '/resourcePHP.php';

/*
 * ***********************************************
 * check for parameters 
 * ***********************************************
 */

$o = new getOptions();
$param = $o->default;
/*
 * ***********************************************
 * create a logger
 * set directory for logfiles and 
 * log to console
 * ***********************************************
 */

$logger = new logToFile(dirname($param['logfile']), $param['console']);
if ($logger->error === '') {
    $logger->logOpen(basename($param['logfile']));
} else {
    $logger = '';
    openlog('websock', LOG_PID, LOG_USER); 
    syslog(LOG_ERR, "can not create loging with ". $param['logfile']);
    closelog();
}
/*
 * *****************************************
 * create server 
 * *****************************************
 */
$server = new websocketServer($param['adress'], $logger, $param['certFile'], $param['pkFile']);
/*
 * ***********************************************
 * set some server variables
 * ***********************************************
 */
$server->maxPerIP = 0;   // 0=unlimited 
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

