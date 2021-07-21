<?php

include __DIR__ . "/../phpClient/websocketCore.php";

class websocketOrg extends websocketCore {

    public $uuid, $connected = false, $chunkSize = 6 * 1024;

    //private $socketMaster;

    function __construct($Address, $Port = '', $app = '/', $uu = '') {

        if (parent::__construct($Address, $Port, $app, $uu) == false) {
            return;
        }

        /*
         * ***********************************************
         * send messages in fragments
         * ***********************************************
         */

        $this->finBit = false;
        $this->writeSocket("Hello");
        $this->finBit = true;
        $this->writeSocket(" from PHP fragmented");
        $respo = $this->readSocket();
        /*
         * ***********************************************
         * not fragmented
         * ***********************************************
         */
        echo $respo;
        $this->writeSocket(" Hallo from PHP not fragmented");
        $respo = $this->readSocket();
        echo "<br>$respo";
    }

}

$x = new websocketOrg("wss://echo.websocket.org");

