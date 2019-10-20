<?php

/*
 * **********************************************
 * write your backend application
 * **********************************************
 */

class resourceWeb extends coreApp {

    private $packet;

    function onData($SocketID, $M) {
        /*
         * *****************************************
         * $M is JSON like
         * {'opcode':task, <followed by whatever is expected based on the value of opcode>}
         * Thsi is just an example used here , you can send what ever you want.
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
            $this->broadCast($SocketID, $M);
            return;
        }
        /*
         * *****************************************
         * unknown opcode-> do nothing
         * *****************************************
         */
    }

    function broadCast($SocketID, $M) {
        foreach ($this->server->Clients as $client) {
            if ($client->Headers === 'websocket') {
                if ($SocketID == $client->ID) {
                    continue;
                }
                $this->server->Write($client->ID, $M);
            }
        }
    }

    function broadCastPong($SocketID) {
        foreach ($this->server->Clients as $client) {
            if ($SocketID == $client->ID) {
                continue;
            }
            if ($this->server->Write($client->ID, 'pong') === false) {
                $this->Close($client->ID);
            }
        }
    }

}

?>
