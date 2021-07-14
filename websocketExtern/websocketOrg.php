<?php

include __DIR__ . "/../phpClient/websocketCore.php";

class websocketOrg extends websocketCore {

    public $uuid, $connected = false, $chunkSize = 6 * 1024;

    //private $socketMaster;

    function __construct($Address, $Port = '', $app = '/', $uu = '') {

        if (parent::__construct($Address, $Port, $app, $uu) == false) {
            return;
        }
        $this->writeSocket("Hello from php");
        $respo = $this->readSocket();
        echo $respo;
    }
}

$x = new websocketOrg("ssl://echo.websocket.org");

