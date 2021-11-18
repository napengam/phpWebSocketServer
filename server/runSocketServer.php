<?php

class runSocketServer {

    function __construct() {
        /*
         * ***********************************************
         * the runtime
         * ***********************************************
         */
        require __DIR__ . '/errorHandler.php';
        require __DIR__ . '/logToFile.php';
        /*
         * ***********************************************
         * inlcude the core server
         * ***********************************************
         */
        require __DIR__ . "/getOptions.php";
        require __DIR__ . "/webSocketServer.php";
        /*
         * **********************************************
         *  your backend applications
         * **********************************************
         */
        require __DIR__ . '/resource.php';
        require __DIR__ . '/resourceDefault.php';
        require __DIR__ . '/resourceWeb.php';
        require __DIR__ . '/resourcePHP.php';
    }

    function run() {
        global $logger;
        /*
         * ***********************************************
         * check for parameters 
         * ***********************************************
         */

        $o = new getOptions();
        $option = $o->default;
        /*
         * ***********************************************
         * create a logger
         * set directory for logfiles and 
         * log to console
         * ***********************************************
         */

        $logger = new logToFile(dirname($option['logfile']), $option['console']);
        if ($logger->error === '') {
            $logger->logOpen(basename($option['logfile']));
        } else {
            $logger = '';
            openlog('websock', LOG_PID, LOG_USER);
            syslog(LOG_ERR, "can not create loging with " . $option['logfile']);
            closelog();
        }
        /*
         * *****************************************
         * create server 
         * *****************************************
         */
        $server = new websocketServer($option['adress'], $logger, $option['certFile'], $option['pkFile']);
        /*
         * ***********************************************
         * set some server variables
         * ***********************************************
         */
        $server->maxPerIP = 0;   // 0=unlimited 
        $server->maxClients = 0; // 0=unlimited 
        $server->pingInterval=0; // unit is seconds; 0=no pings to clients
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
(new runSocketServer())->run();

