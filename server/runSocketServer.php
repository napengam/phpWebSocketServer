<?php

/*
 * **************************
 * include address and port like:
 * $Address=[ssl:// | tcp://]server.at.com
 * $Port=number
 * **************************
 */
include '../include/certPath.inc.php';
include '../include/adressPort.inc.php';
/*
 * ***********************************************
 * inlcude the core server
 * ***********************************************
 */

if (stripos('LINUX', PHP_OS) !== false) {
    if (isSecure($Address)) {
        include "webSocketServerSSL.php";
    } else {
        include "webSocketServer.php";
    }
} else {
    include "webSocketServer.php";
}

/*
 * ***********************************************
 * extend/customize Server
 * ***********************************************
 */

class customServer extends WebSocketServer {

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
            $this->Log("No data $packet // $M from  #$SocketID");
            return;
        }

        $this->packet = $packet;
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

        if ($packet->opcode === 'uuid') {
            /*
             * *****************************************
             * client registers
             * *****************************************
             */
            $this->Clients[$SocketID]->uuid = $packet->message;
            $this->log("Broadcast $M");
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
        /*
         * *****************************************
         * no opcode-> broadcast to all
         * *****************************************
         */
        $this->log("Broadcast $M");
        $this->broadCast($SocketID, $M);
    }

    function onOpen($SocketID) {
        /*
         * **************************
         * clients should wait for this response
         * to be sure server is ready
         * **************************
         */
        $this->Log("Telling Client to start on  #$SocketID");
        $msg = (object) Array('opcode' => 'ready', 'os' => $this->serveros);
        $this->Write($SocketID, json_encode($msg));
    }

    function feedback($packet) {
        foreach ($this->Clients as $client) {
            if ($packet->uuid == $client->uuid && $client->Headers === 'websocket') {
                $this->Write($client->ID, json_encode($packet));
                return;
            }
        }
    }

    function broadCast($SocketID, $M) {
        foreach ($this->Clients as $client) {
            if ($client->Headers === 'websocket') {
                if ($SocketID == $client->ID) {
                    continue;
                }
                $this->Write($client->ID, $M);
            }
        }
    }

    function broadCastPong($SocketID) {
        foreach ($this->Clients as $client) {
            if ($SocketID == $client->ID) {
                continue;
            }
            if ($this->Write($client->ID, 'pong') === false) {
                $this->Close($client->ID);
            }
        }
    }

}

function isSecure($Address) {
    $arr = explode('://', $Address);
    if (count($arr) > 1) {
        if (strncasecmp($arr[0], 'ssl', 3) == 0) {
            return true;
        }
    }
    return false;
}

/*
 * *****************************************
 * start server 
 * *****************************************
 */


$customServer = new customServer($Address, $Port, $keyAndCertFile, $pathToCert);

//$customServer->logToDisplay = false;
$customServer->logToFile = false;
$customServer->Start();
?>
