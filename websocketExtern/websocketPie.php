<?php

include __DIR__ . "/../phpClient/websocketCore.php";

class websocketPie extends websocketCore {

   
    function __construct($Address) {

        if (parent::__construct($Address) == false) {
            return;
        }
       
        $respo = $this->readSocket();
        echo $respo;
    }

}

$x = new websocketPie("wss://demo.piesocket.com/v3/channel_1?api_key=demo");

