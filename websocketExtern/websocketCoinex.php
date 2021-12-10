<?php

include __DIR__ . "/../phpClient/websocketCore.php";

class websocketCoinex extends websocketCore {

//private $socketMaster;

    function __construct($Address) {

        if (parent::__construct($Address) == false) {
            return;
        }


        $this->writeSocket(json_encode([
            "method" => "server.ping",
            "params" => [],
            "id" => 11]
        ));

        $respo = $this->readSocket();
        echo var_dump(json_decode($respo));
    }

}

$x = new websocketCoinex("wss://socket.coinex.com/");

