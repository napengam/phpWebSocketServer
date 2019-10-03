<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of coreApp
 *
 * @author Heinz
 */
class coreApp {

    public $server;

    /*
     * ***********************************************
     * Overwrite these function, when needed, in an 
     * application class
     * registered with the socket server
     * ***********************************************
     */

    function onOpen($SocketID) {
        $this->server->Log("Telling Client to start on  #$SocketID");
        $msg = (object) Array('opcode' => 'ready', 'os' => $this->server->serveros);
        $this->server->Write($SocketID, json_encode($msg));
    }

    function onData($SocketID, $M) { // date has benn received from client        
    }

    function onClose($SocketID) { // ...socket has been closed AND deleted        
    }

    function onError($SocketID, $M) { // ...any connection-releated error   
    }

    function onOther($SocketID, $M) { // ...any connection-releated notification
    }

    function onOpening($SocketID) { // ...being accepted and added to the client list
    }

    final function registerServer($server) {
        $this->server = $server;
    }

}
