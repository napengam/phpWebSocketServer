<?php

// WebSocketServer implementation in PHP
// by Bryan Bliewert, nVentis@GitHub
// https://github.com/nVentis/PHP-WebSocketServer
// modified by Heinz Schweitzer
// https://github.com/napengam/phpWebSocketServer 
// to work for communicating over secure websocket wss://
// and accept any other socket connection by PHP processes or other 


class WebSocketServer {
    
    use coreFunc; // TRAIT to implement various methods

    public
            $logToFile = false,
            $logFile = "log.txt",
            $logToDisplay = true,
            $Sockets = [],
            $bufferLength = 2048 * 100,
            $maxClients = 20,
            $errorReport = E_ALL,
            $timeLimit = 0,
            $implicitFlush = true,
            $serveros = 'WINDOW';
    protected
            $Address,
            $Port,
            $socketMaster,
            $Clients = [];

    function __construct($Address, $Port, $keyAndCertFile = '', $pathToCert = '') {

        //$this->core = new coreFunc();

        $this->socketMaster = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!is_resource($this->socketMaster)) {
            $this->Log("The master socket could not be created: " . socket_strerror(socket_last_error()), true);
        }
        socket_set_option($this->socketMaster, SOL_SOCKET, SO_REUSEADDR, 1);
        if (!socket_bind($this->socketMaster, $Address, $Port)) {
            $this->Log("Can't bind on master socket: " . socket_strerror(socket_last_error()), true);
        }
        if (!socket_listen($this->socketMaster, $this->maxClients)) {
            $this->Log("Can't listen on master socket: " . socket_strerror(socket_last_error()), true);
        }
        $this->Sockets["m"] = $this->socketMaster;
        $this->Log("Server initilaized on $Address:$Port  ; no SSL");

        error_reporting($this->errorReport);
        set_time_limit($this->timeLimit);
        if ($this->implicitFlush) {
            ob_implicit_flush();
        }
    }

    public function Start() {
        $this->Log("Starting server...");
        $a = true;
        $nulll = NULL;
        while ($a) {
            //   $a = false;
            $socketArrayRead = $this->Sockets;
            $socketArrayWrite = $socketArrayExceptions = NULL;
            @socket_select($socketArrayRead, $socketArrayWrite, $socketArrayExceptions, NULL);
            foreach ($socketArrayRead as $Socket) {
                $SocketID = intval($Socket);

                if ($Socket === $this->socketMaster) {
                    $Client = socket_accept($Socket);

                    if (!is_resource($Client)) {
                        $this->onError($SocketID, "Connection could not be established");
                        continue;
                    } else {
                        $this->addClient($Client);
                        $this->onOpening($SocketID);
                    }
                } else {
                    $receivedBytes = @socket_recv($Socket, $dataBuffer, $this->bufferLength, 0);
                    if ($receivedBytes === false) {
                        // on error

                        $sockerError = socket_last_error($Socket);
                        $socketErrorM = socket_strerror($sockerError);
                        if ($sockerError >= 100) {
                            $this->onError($SocketID, "Unexpected disconnect with error $sockerError [$socketErrorM]");
                            $this->Close($Socket);
                        } else {
                            $this->onOther($SocketID, "Other socket error $sockerError [$socketErrorM]");
                            $this->Close($Socket);
                        }
                    } else if ($receivedBytes == 0) {
                        // no headers received (at all) --> disconnect
                        $SocketID = $this->Close($Socket);
                        $this->onError($SocketID, "Client disconnected - TCP connection lost");
                    } else {
                        // no error, --> check handshake
                        $Client = $this->getClient($Socket);
                        if ($Client->Handshake == false) {
                            if (strpos(str_replace("\r", '', $dataBuffer), "\n\n") === false) { // headers have not been completely received --> wait --> handshake
                                $this->onOther($SocketID, "Continue receving headers");
                                continue;
                            }
                            $this->Handshake($Socket, $dataBuffer);
                        } else {
                            if ($this->Clients[$SocketID]->Headers !== 'websocket') {
                                //  $l = substr($dataBuffer, 0, 32) * 1; // <== length of data to come from client
                                //$dataBuffer = socket_read($Socket, $l * 1); //<== data from client
                            }
                            if ($dataBuffer === false) {
                                $this->Close($Socket);
                            } else if (strlen($dataBuffer) == 0) {
                                // no headers received (at all) --> disconnect
                                $SocketID = $this->Close($Socket);
                                $this->onError($SocketID, "Client disconnected - TCP connection lost");
                            } else {
                                $this->log("Received bytes = " . strlen($dataBuffer));
                                $this->Read($SocketID, $dataBuffer);
                            }
                        }
                    }
                }
            }
        }
    }

   
    public function Close($SocketID) {
        if (is_resource($SocketID)) {
            $SocketID = intval($SocketID);
        }
        socket_shutdown($this->Sockets[$SocketID]);
        unset($this->Clients[$SocketID]);
        unset($this->Sockets[$SocketID]);
        $this->onClose($SocketID);
        return $SocketID;
    }

    protected function Handshake($Socket, $Buffer) {

        $addHeader = [];
        if ($Buffer == "php process\n\n") {
            $SocketID = intval($Socket);
            $this->Clients[$SocketID]->Headers = 'tcp';
            $this->Clients[$SocketID]->Handshake = true;
            $this->onOpen($SocketID);
            return;
        }
        $SocketID = intval($Socket);
        $magicGUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
        $Headers = [];
        $Lines = explode("\n", $Buffer);
        foreach ($Lines as $Line) {
            if (strpos($Line, ":") !== false) {
                $Header = explode(":", $Line, 2);
                $Headers[strtolower(trim($Header[0]))] = trim($Header[1]);
            } else if (stripos($Line, "get ") !== false) {
                preg_match("/GET (.*) HTTP/i", $Buffer, $reqResource);
                $Headers['get'] = trim($reqResource[1]);
            }
        }

        if (!isset($Headers['host']) ||
                !isset($Headers['sec-websocket-key']) ||
                (!isset($Headers['upgrade']) || strtolower($Headers['upgrade']) != 'websocket') ||
                (!isset($Headers['connection']) || strpos(strtolower($Headers['connection']), 'upgrade') === FALSE)) {
            $addHeader[] = "HTTP/1.1 400 Bad Request";
        }
        if (!isset($Headers['sec-websocket-version']) || strtolower($Headers['sec-websocket-version']) != 13) {
            $addHeader[] = "HTTP/1.1 426 Upgrade Required\r\nSec-WebSocketVersion: 13";
        }
        if (!isset($Headers['get'])) {
            $addHeader[] = "HTTP/1.1 405 Method Not Allowed\r\n\r\n";
        }
        if (count($addHeader) > 0) {
            $addh = implode("\r\n", $addHeader);
            socket_write($Socket, $addh, strlen($addh));
            $this->onError($SocketID, "Handshake aborted - [" . trim($addh) . "]");
            return $this->Close($Socket);
        }
        $Token = "";
        $sah1 = sha1($Headers['sec-websocket-key'] . $magicGUID);
        for ($i = 0; $i < 20; $i++) {
            $Token .= chr(hexdec(substr($sah1, $i * 2, 2)));
        }
        $Token = base64_encode($Token) . "\r\n";
        $addHeaderOk = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $Token\r\n";
        @socket_write($Socket, $addHeaderOk, strlen($addHeaderOk));

        $this->Clients[$SocketID]->Headers = 'websocket';
        $this->Clients[$SocketID]->Handshake = true;
        $this->onOpen($SocketID);
    }

    public function Read($SocketID, $M) {
        if ($this->Clients[$SocketID]->Headers === 'websocket') {
//            $M = $this->core->Decode($M);
            $M = $this->Decode($M);
        }
        $this->Write($SocketID, json_encode((object) ['opcode' => 'next', 'uuid' => $this->Clients[$SocketID]->uuid]));
        $this->onData($SocketID, ($M));
    }

    public function Write($SocketID, $M) {
        if ($this->Clients[$SocketID]->Headers === 'websocket') {
//            $M = $this->core->Encode($M);
            $M = $this->Encode($M);
        }
        if (socket_write($this->Sockets[$SocketID], $M, strlen($M)) === false) {
            return false;
        }
    }

    // Methods to be configured by the user; executed directly after...
    function onOpen($SocketID) { //...successful handshake
        $this->Log("Handshake with socket #$SocketID successful");
    }

    function onData($SocketID, $M) { // ...message receipt; $M contains the decoded message
        $this->Log("Received " . strlen($M) . " Bytes from socket #$SocketID");
    }

    function onClose($SocketID) { // ...socket has been closed AND deleted
        $this->Log("Connection closed to socket #$SocketID");
    }

    function onError($SocketID, $M) { // ...any connection-releated error
        $this->Log("Socket $SocketID - " . $M);
    }

    function onOther($SocketID, $M) { // ...any connection-releated notification
        $this->Log("Socket $SocketID - " . $M);
    }

    function onOpening($SocketID) { // ...being accepted and added to the client list
        $this->Log("New client connecting on socket #$SocketID");
    }

}

?>