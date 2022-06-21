<?php

include __DIR__ . "/../phpClient/websocketCore.php";

class websocketBinance extends websocketCore {

   
    //private $socketMaster;

    function __construct($Address) {

        if (parent::__construct($Address) == false) {
            return;
        }

        $respo = $this->readSocket();
        echo var_dump(json_decode($respo));
    }

}

$x = new websocketBinance("wss://stream.binance.com:9443/ws/btcusdt@ticker");

