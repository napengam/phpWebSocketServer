<?php

class resource {

   
    private $methods = ['broadCast', 'feedback', 'echo', 'Log', 'Close'];

    /*
     * ***********************************************
     * Overwrite these functions, when needed, in an 
     * application class then register with the  server
     * ***********************************************
     */

    final function broadCast($SocketID, $M) {
        call_user_func($this->broadCastS, $SocketID, $M);
    }

    final function feedback($packet) {
        call_user_func($this->feedbackS, $packet);
    }

    final function echo($sockid, $packet) {
        call_user_func($this->echoS, $sockid, $packet);
    }

    final function Log($m) {
        call_user_func($this->LogS, $m);
    }

    final function Close($SocketID) {
        call_user_func($this->CloseS, $SocketID);
    }

    function onOpen($SocketID) {
        
    }

    function onData($SocketID, $M) { //... a message from client        
    }

    function onClose($SocketID) { // ...socket has been closed AND deleted        
    }

    function onError($SocketID, $M) { // ...any connection-releated error   
    }

    final public function registerServerMethods($server) {
        /*
         * ***********************************************
         * extract methods from server neede in clients
         * **********************************************
         */
        foreach ($this->methods as $index => $meth) {
            $this->{$this->methods[$index] . 'S'} = [$server, $meth];
        }
    }

    final function getPacket($M) {
        $packet = json_decode($M);
        $err = json_last_error();
        if ($err) {
            $packet = (object) ['opcode' => 'jsonerror', 'message' => $err];
        }
        return $packet;
    }
}
