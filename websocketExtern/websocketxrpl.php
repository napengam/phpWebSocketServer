<?php

include __DIR__ . "/../phpClient/websocketCore.php";

class websocketXrpl extends websocketCore {

    function __construct($Address) {

        if (parent::__construct($Address) == false) {
            return;
        }
        $this->writeSocket(' {"command": "server_info"} ');
        $respo = $this->readSocket();
        echo $respo;
    }

}

$x = new websocketXrpl("wss://xrplcluster.com");

