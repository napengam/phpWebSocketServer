<?php

include __DIR__ . "/../phpClient/websocketCore.php";

class websocketPie extends websocketCore {

    public $uuid, $connected = false, $chunkSize = 6 * 1024;

    //private $socketMaster;

    function __construct($Address, $Port = '', $app = '/', $uu = '') {

        if (parent::__construct($Address, $Port, $app, $uu) == false) {
            return;
        }
       
        $respo = $this->readSocket();
        echo $respo;
    }

}

$x = new websocketPie("wss://demo.piesocket.com",'',"/v3/1?api_key=oCdCMcMPQpbvNjUIzqtvF1d2X2okWpDQj4AwARJuAgtjhzKxVEjQU6IdCjwm");

