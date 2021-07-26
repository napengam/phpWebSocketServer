<?php

include __DIR__ . "/../phpClient/websocketCore.php";

class websocketOrg extends websocketCore {

   
    function __construct($Address) {

        if (parent::__construct($Address) == false) {
            return;
        }

        /*
         * ***********************************************
         * send messages in fragments
         * ***********************************************
         */

        $this->finBit = false; // turn fragmenting on
        
        $this->writeSocket("Hello"); //first fragment
        
        $this->writeSocket(" from "); // next fragment
        
        $this->finBit = true; // last fragment, turn  off now
        $this->writeSocket("PHP fragmented");
        
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

