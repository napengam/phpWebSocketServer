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

$x = new websocketPie("wss://demo.piesocket.com/v3/1?api_key=oCdCMcMPQpbvNjUIzqtvF1d2X2okWpDQj4AwARJuAgtjhzKxVEjQU6IdCjwm");

