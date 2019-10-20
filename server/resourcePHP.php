<?php

/*
 * **********************************************
 * write your backend application for resourec /php
 * **********************************************
 */

class resourcePhp extends coreApp {

    private $packet; //, $server;

    function onData($SocketID, $M) {
        /*
         * *****************************************
         * $M is JSON like
         * {'opcode':task, <followed by whatever is expected based on the value of opcode>}
         * Thsi is just an example used here , you can send what ever you want.
         * *****************************************
         */
        $packet = json_decode($M);


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

    function feedback($packet) {
        foreach ($this->server->Clients as $client) {
            if ($packet->uuid == $client->uuid && $client->Headers === 'websocket') {
                $this->server->Write($client->ID, json_encode($packet));
                return;
            }
        }
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

}

?>
