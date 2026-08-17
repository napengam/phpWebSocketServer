<?php

require __DIR__ . '/classLoader.php';

class runSocketServer {

    function __construct() {
        
    }

    function run() {
        global $logger;
        /*
         * ***********************************************
         * check for parameters 
         * ***********************************************
         */

        // $o = new GetOptionsx('phpWebSocketServer', 'websock.ini');
        $option = GetAllConfig::load()['websocketserver'];
        //$option = $o->getConfig();
        /*
         * ***********************************************
         * create a logger
         * set directory for logfiles and 
         * log to console
         * ***********************************************
         */
        $logger = new logToFile($option['logfile'], 'phpwebsocketserver', '', $option['console']);
        /*
         * *****************************************
         * create server 
         * *****************************************
         */
        //$server = new webSocketServer($option['adress'], $logger, $option['certFile'], $option['pkFile']);
        $server = new webSocketServer($option['adress'], $logger);
        /*
         * ***********************************************
         * set some server variables
         * ***********************************************
         */
        $server->maxPerIP = 0;   // 0=unlimited 
        $server->maxClients = 0; // 0=unlimited 
        $server->pingInterval = 0; // unit is seconds; 0=no pings to clients
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
    }
}

/*
 * ***********************************************
 * start 
 * ***********************************************
 */

$paths = [
    'server',
    'phpClient'
];

ClassLoader::load($paths);

(new runSocketServer())->run();

