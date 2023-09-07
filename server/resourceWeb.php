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

        if ($packet->opcode === 'jsonerror') {
            $this->Log("jsonerror closing #$SocketID");
            $this->Close($SocketID);
            return;
        }
        if ($packet->opcode === 'quit') {
            /*
             * *****************************************
             * client quits
             * *****************************************
             */
            $this->Log("QUIT; Connection closed to socket #$SocketID");
            $this->Close($SocketID);
            return;
        }
        if ($packet->opcode === 'feedback') {
            /*
             * *****************************************
             * send feedback to client with uuid found
             * in $packet
             * *****************************************
             */
            $this->feedback($packet);
            return;
        }
        if ($packet->opcode === 'echo') {
            $this->echo($SocketID, $packet);
            return;
        }
        if ($packet->opcode === 'broadcast') {
            $this->broadCast($SocketID, $M);
            return;
        }
        /*
         * *****************************************
         * unknown opcode-> do nothing or close socket
         * *****************************************
         * $this->server>close($SocketID);
         *  
         */
    }

    function onError($SocketID, $M) {
        
    }
}
