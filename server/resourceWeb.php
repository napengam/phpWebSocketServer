<?php

/*
 * **********************************************
 * write your backend application
 * **********************************************
 */

class resourceWeb extends resource {

    private $packet;

    function onData($SocketID, $M) {
        /*
         * *****************************************
         * $M is JSON like
         * {'opcode':task, <followed by whatever is expected based on the value of opcode>}
         * This is just an example used here , you can send what ever you want.
         * *****************************************
         */


        $packet = $this->getPacket($M);
        $this->packet = $packet;

        if ($packet->opcode === 'quit') {
            /*
             * *****************************************
             * client quits
             * *****************************************
             */
            $this->server->Log("QUIT; Connection closed to socket #$SocketID");
            $this->server->Close($SocketID);
            return;
        }

        if ($packet->opcode === 'uuid') {
            /*
             * *****************************************
             * client registers
             * *****************************************
             */
            $this->server->Clients[$SocketID]->uuid = $packet->message;
            $this->server->log("Broadcast $M");
            return;
        }
        if ($packet->opcode === 'broadcast') {
            $this->server->broadCast($SocketID, $M);
            return;
        }
        /*
         * *****************************************
         * unknown opcode-> do nothing
         * *****************************************
         */
    }

    function onError($SocketID, $M) {
        
    }

}
