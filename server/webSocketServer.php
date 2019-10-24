<?php

include_once '../include/certPath.inc.php';
include_once '../include/adressPort.inc.php';
include_once 'coreFunc.php';

class WebSocketServer {

    use coreFunc; // TRAIT to implement various methods

    public           
            $logging = '',
            $Sockets = [],
            $bufferLength = 10 * 4096,
            $maxClients = 20,
            $errorReport = E_ALL,
            $timeLimit = 0,
            $implicitFlush = true,
            $Clients = [],
            $opcode = 1, // text frame  
            $serveros;
    protected
            $Address,
            $Port,
            $socketMaster,
            $allApps = [];

    function __construct($Address, $Port, $logger, $keyAndCertFile = '', $pathToCert = '') {

        $errno = 0;
        $errstr = '';
        $this->logging = $logger;

        /*
         * ***********************************************
         * below has to be done once ,if server runs on system using
         * letsencrypt
         * 
         * openssl pkcs12 -export -in hostname.crt -inkey hostname.key -out hostname.p12
         * openssl pkcs12 -in hostname.p12 -nodes -out hostname.pem
         * ***********************************************
         */
        $usingSSL = '';
        $context = stream_context_create();
        if ($this->isSecure($Address)) {
            stream_context_set_option($context, 'ssl', 'local_cert', $keyAndCertFile);
            stream_context_set_option($context, 'ssl', 'capth', $pathToCert);
            stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
            $usingSSL = "using SSL";
        }
        $socket = stream_socket_server("$Address:$Port", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

        $this->Log("Server initialized on " . PHP_OS . "  $Address:$Port $usingSSL");
        if (!$socket) {
            $this->Log("Error $errno creating stream: $errstr", true);
            exit;
        }
        $this->serveros = PHP_OS;
        $this->Sockets["m"] = $socket;
        $this->socketMaster = $socket;

        error_reporting($this->errorReport);
        set_time_limit($this->timeLimit);
        if ($this->implicitFlush) {
            ob_implicit_flush();
        }
    }

    function isSecure(&$Address) {
        $arr = explode('://', $Address);
        if (count($arr) > 1) {
            if (strncasecmp($arr[0], 'ssl', 3) == 0) {
                return true;
            }
            $Address = $arr[1]; // just the host
        }
        return false;
    }

    public function Start() {

        $this->Log("Starting server...");
        foreach ($this->allApps as $appName => $class) {
            $this->Log("Registered resource : $appName");
        }
        $a = true;
        $nulll = NULL;
        while ($a) {

            $socketArrayRead = $this->Sockets;
            $socketArrayWrite = $socketArrayExceptions = NULL;
            stream_select($socketArrayRead, $socketArrayWrite, $socketArrayExceptions, $nulll);
            foreach ($socketArrayRead as $Socket) {
                $SocketID = intval($Socket);

                if ($Socket === $this->socketMaster) {
                    $Client = stream_socket_accept($Socket);
                    if (!is_resource($Client)) {
                        $this->onError($SocketID, "Connection could not be established");
                        continue;
                    } else {
                        $this->addClient($Client);
                        $this->onOpening($SocketID);
                    }
                } else {

                    $Client = $this->Clients[$SocketID];

                    if ($Client->Handshake == false) {
                        $dataBuffer = fread($Socket, $this->bufferLength);
                        if (strpos(str_replace("\r", '', $dataBuffer), "\n\n") === false) {
                            $this->onOther($SocketID, "Continue receving headers");
                            continue;
                        }
                        if ($this->Handshake($Socket, $dataBuffer)) {
                            if ($this->Clients[$SocketID]->app === NULL) {
                                $this->Close($Socket);
                                $this->log('Application incomplete');
                            } else {
                                $this->onOpen($SocketID);
                            }
                        }
                        continue;
                    }

                    $dataBuffer = fread($Socket, $this->bufferLength);
                    if ($dataBuffer === false) {
                        $this->Close($Socket);
                    } else if (strlen($dataBuffer) == 0) {
                        $this->onError($SocketID, "Client disconnected - TCP connection lost");
                        $SocketID = $this->Close($Socket);
                    } else {
                        $this->Read($SocketID, $dataBuffer);
                    }
                }
            }
        }
    }

    public function Close($Socket) {
        if (is_int($Socket)) {
            $Socket = $this->Sockets[$Socket];
        }
        stream_socket_shutdown($Socket, STREAM_SHUT_RDWR);
        $SocketID = intval($Socket);
        $this->onClose($SocketID);
        unset($this->Clients[$SocketID]);
        unset($this->Sockets[$SocketID]);
        return $SocketID;
    }

    public function Read($SocketID, $message) {
        $client = $this->Clients[$SocketID];
        if ($client->Headers === 'websocket') {
            $message = $this->Decode($message);
            if ($this->opcode == 10) { //pong
                $this->opcode = 1; // text frame 
                $this->log("Unsolicited Pong frame received from socket #$SocketID"); // just ignore
                return;
            }
            if ($this->opcode == 8) { //Connection Close Frame             
                $this->opcode = 1; // text frame 
                $this->log("Connection Close frame received from socket #$SocketID");
                $this->Close($SocketID);
                return;
            }
        }
        $this->Write($SocketID, json_encode((object) ['opcode' => 'next']));
        if ($this->serverCommand($client, $message)) {
            return;
        }

        if ($client->bufferON) {
            $client->buffer[] = $message;
            return;
        }

        $this->onData($SocketID, $message);
    }

    public function Write($SocketID, $message) {
        if ($this->Clients[$SocketID]->Headers === 'websocket') {
            $message = $this->Encode($message);
        }
        return fwrite($this->Sockets[$SocketID], $message, strlen($message));
    }

    public function broadCast($SocketID, $M) {
        $ME = $this->Encode($M);
        foreach ($this->Clients as $client) {
            if ($client->Headers === 'websocket') {
                if ($SocketID == $client->ID) {
                    continue;
                }
                fwrite($this->Sockets[$client->ID], $ME, strlen($ME));
            }
        }
    }

    public function registerResource($name, $app) {
        $this->allApps[$name] = $app;
        foreach (['registerServer', 'onOpen', 'onData', 'onClose', 'onError', 'onOther'] as $method) {
            if (!method_exists($app, $method)) {
                $this->allApps[$name] = NULL;
                return false;
            }
        }
        $app->registerServer($this);
        return true;
    }

    private function serverCommand($client, &$message) {
        if ($message === 'bufferON') {
            $client->bufferON = true;
            $client->buffer = [];
            $this->Log('Buffering ON');
            return true;
        }
        if ($message === 'bufferOFF') {
            $client->bufferON = false;
            $message = implode('', $client->buffer);
            $client->buffer = [];
            $this->Log('Buffering OFF');
        }

        return false;
    }

    function Log($m) {
        if ($this->logging) {
            $this->logging->log($m);
        }
    }

// Methods to be configured by the user; executed directly after...
    function onOpen($SocketID) { //...successful handshake
        $this->Log("Handshake with socket #$SocketID successful");
        $this->Clients[$SocketID]->app->onOpen($SocketID);
    }

    function onData($SocketID, $message) { // ...message receipt; $message contains the decoded message
        // $this->Log("Received " . strlen($message) . " Bytes from socket #$SocketID");
        $this->Clients[$SocketID]->app->onData($SocketID, ($message));
    }

    function onClose($SocketID) { // ...socket has been closed AND deleted
        $this->Log("Connection closed to socket #$SocketID");
        $this->Clients[$SocketID]->app->onClose($SocketID);
    }

    function onError($SocketID, $message) { // ...any connection-releated error
        $this->Log("Socket $SocketID - " . $message);
        $this->Clients[$SocketID]->app->onError($SocketID, $message);
    }

    function onOther($SocketID, $message) { // ...any connection-releated notification
        $this->Log("Socket $SocketID - " . $message);
        $this->Clients[$SocketID]->app->onOther($SocketID, $message);
    }

    function onOpening($SocketID) { // ...being accepted and added to the client list
        $this->Log("New client connecting on socket #$SocketID");
    }

}

?>