<?php

/*
 * **********************************************
 * write your backend application for resource /php
 * **********************************************
 */

class resourcePhp extends resource {

    private $packet; //, $server;

    function onData($SocketID, $M) {
        /*
         * *****************************************
         * $M is JSON like
         * {'opcode':task, <followed by whatever is expected based on the value of opcode>}
         * Thsi is just an example used here , you can send what ever you want.
         * *****************************************
         */
        $packet = $this->getPacket($M);

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
             * $packet
             * *****************************************
             */
            $this->feedback($packet);
            return;
        }
        if ($packet->opcode === 'echo') {
            /*
             * *****************************************
             * echo back to client
             * *****************************************
             */
            $this->echo($SocketID, $packet);
            return;
        }
        if ($packet->opcode === 'broadcast') {
            $this->broadCast($SocketID, $M);
            return;
        }
        /*
         * *****************************************
         * unknown opcode-> do nothing
         * *****************************************
         */
    }

}
