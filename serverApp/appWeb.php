<?php

/*
 * **********************************************
 * write your backend application
 * **********************************************
 */

class appWeb extends coreApp {

    private $packet;

    function onData($SocketID, $M) {
        /*
         * *****************************************
         * $M is JSON like
         * {'opcode':task, <followed by whatever is expected based on the value of opcode>}
         * Thsi is just an example used here , you can send what ever you want.
         * *****************************************
         */


        $packet = json_decode($M);


        if ($packet == NULL) {
            /*
             * *****************************************
             * probably a pong request from a client
             * We see this only when client connects via
             * websocket from IE11 or EDGE.
             * *******************************************
             */
            $this->server->Log("No data $packet // $M from  #$SocketID");
            return;
        }

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
        
        /*
         * *****************************************
         * no opcode-> broadcast to all
         * *****************************************
         */
        $this->server->log("Broadcast $M");
        $this->broadCast($SocketID, $M);
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
