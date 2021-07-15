<?php

include __DIR__ . "/../phpClient/websocketCore.php";

class webSocketBinance extends websocketCore {

    public $uuid, $connected = false, $chunkSize = 6 * 1024;

    //private $socketMaster;

    function __construct($Address, $Port = '', $app = '/', $uu = '') {

        if (parent::__construct($Address, $Port, $app, $uu) == false) {
            return;
        }

        $respo = $this->readSocket();
        echo var_dump(json_decode($respo));
    }

}

$x = new webSocketBinance("wss://stream.binance.com", "9443", "/ws/btcusdt@ticker");

