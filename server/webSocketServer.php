<?php

require __DIR__ . '/RFC6455.php';

class webSocketServer {

    use RFC6455; // TRAIT to implement methods required by RFC6455

    public
            $logging = '',
            $Sockets = [],
            $bufferLength = 10 * 4096,
            $bufferChunk = 8 * 1024, // client sends in chuncks of 6kBytes 
            $errorReport = E_ALL,
            $timeLimit = 0,
            $implicitFlush = true,
            $Clients = [],
            $clientIPs = [],
            $maxPerIP = 0, // maximum number of websocket connections from one IP 0=unlimited
            $allowedIP = [], // ['127.0.0.1','::1'] 
            $opcode = 1, // text frame  
            $maxChunks = 100; // avoid flooding during bufferON
    protected
            $Address,
            $Port,
            $socketMaster,
            $allApps = [];

    function __construct($Address, $Port, $logger, $certFile = '', $pkFile = '') {

        $errno = 0;
        $errstr = '';
        $this->logging = $logger;

        /*
         * ***********************************************
         * as of 2021-07-21 context is set with 
         * cert.pem and privkey.pem
         * ***********************************************
         */
        $usingSSL = '';
        $context = stream_context_create();
        if ($this->isSecure($Address)) {
            stream_context_set_option($context, 'ssl', 'local_cert', $certFile);
            stream_context_set_option($context, 'ssl', 'local_pk', $pkFile);
//            stream_context_set_option($context, 'ssl', 'capath', $pathToCert);
//            stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
            $usingSSL = "ssl://";
        }
        $socket = stream_socket_server("$usingSSL$Address:$Port", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

        $this->Log("Server initialized on " . PHP_OS . "  $Address:$Port $usingSSL");
        if (!$socket) {
            $this->Log("Error $errno creating stream: $errstr", true);
            exit;
        }

        $this->Sockets[intval($socket)] = $socket;
        $this->socketMaster = $socket;
        $this->allowedIP[] = gethostbyname($Address);
        $this->allowedIP[] = '::1';

        error_reporting($this->errorReport);
        set_time_limit($this->timeLimit);
        if ($this->implicitFlush) {
            ob_implicit_flush();
        }
    }

    private function isSecure(&$Address) {
        $arr = explode('://', $Address);
        if (count($arr) > 1) {
            if (strncasecmp($arr[0], 'ssl', 3) == 0 || strncasecmp($arr[0], 'wss', 3) == 0) {
                $Address = $arr[1];
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
        $socketArrayWrite = $socketArrayExceptions = NULL;
        while ($a) {
            $socketArrayRead = $this->Sockets;
            $ncon = stream_select($socketArrayRead, $socketArrayWrite, $socketArrayExceptions, 1, 000);
            if ($ncon === 0) {
                /*
                 * ***********************************************
                 * no news after one second; we can do other tasks.
                 * Here we continue to wait for another second 
                 * ***********************************************
                 */

//                if ($this->pingClients()) {
//                    $this->Log("Ping Clients");
//                }
                continue;
            }
            foreach ($socketArrayRead as $Socket) {
                $SocketID = intval($Socket);
                if ($Socket === $this->socketMaster) {
                    /*
                     * ***********************************************
                     * new client
                     * ***********************************************
                     */
                    $clientSocket = stream_socket_accept($Socket);
                    if (!is_resource($clientSocket)) {
                        $this->Log("$SocketID, Connection could not be established");
                        continue;
                    }
                    /*
                     * ***********************************************
                     * get IP:Port of client
                     * ***********************************************
                     */
                    $ipport = stream_socket_get_name($clientSocket, true);
                    $ip = $this->extractIP($ipport); // can be ipv4 or ipv6
                    $this->Log("Connecting from IP: $ip");
                    $SocketID = intval($clientSocket);
                    $this->Clients[$SocketID] = (object) [
                                'ID' => $SocketID,
                                'uuid' => '',
                                'clientType' => null,
                                'Handshake' => false,
                                'timeCreated' => time(), // not used yet
                                'bufferON' => false,
                                'fin' => true, // RFC6455 final fragment in message 
                                'buffer' => [], // buffers message chunks
                                'app' => NULL,
                                'ip' => $ip,
                                'fyi' => '',
                                'expectPong' => false
                    ];
                    $this->Sockets[$SocketID] = $clientSocket;
                    $this->Log("New client connecting from $ipport on socket #$SocketID\r\n");
                    continue; // done so far for this new client
                }

                /*
                 * ***********************************************
                 * setting unbuffered read, could be dangerous
                 * because a client can send unlimited amount of
                 * data and block the server. Therefor I do not 
                 * use this option. Client has to send long messages
                 * in chunks.
                 * ***********************************************
                 */

//stream_set_read_buffer($Socket, 0); // no buffering hgs 01.05.2021


                /*
                 * ***********************************************
                 * read data from socket and check
                 * ***********************************************
                 */

                $dataBuffer = fread($Socket, $this->bufferLength);
                if ($dataBuffer === false ||
                        strlen($dataBuffer) == 0 ||
                        strlen($dataBuffer) >= $this->bufferChunk) {  // to avoid malicious overload 
                    $this->onError($SocketID, "Client disconnected by Server - TCP connection lost");
                    $this->Close($Socket);
                    continue;
                }
                $Client = $this->Clients[$SocketID];

                if ($Client->Handshake) {
                    /*
                     * ***********************************************
                     * Handshake and checks have passsed.
                     * get message from client
                     * ***********************************************
                     */

                    $message = $this->extractMessage($SocketID, $dataBuffer);
                    if ($message != '') {
                        /*
                         * ***********************************************
                         * route message to application class 
                         * ***********************************************
                         */
                        $Client->app->onData($SocketID, $message);
                    }
                    continue;
                }
                /*
                 * ***********************************************
                 * handshake
                 * ***********************************************
                 */

                if ($this->Handshake($Socket, $dataBuffer) === false) {
                    continue; // something is wrong 
                }
                /*
                 * ***********************************************
                 * handshake according RFC 6455 is ok .
                 * Now,for this client, check for apps and connections
                 * ***********************************************
                 */
                if ($this->specificChecks($SocketID) === false) {
                    continue; // something is wrong
                }
                /*
                 * ***********************************************
                 * all checks passed now let client work
                 * ***********************************************
                 */
                $this->Log("Telling Client to start on  #$SocketID");
                $uuid = $this->guidv4();
                $msg = (object) ['opcode' => 'ready', 'uuid' => $uuid];
                $this->Clients[$SocketID]->uuid = $uuid;
                $this->Write($SocketID, json_encode($msg));
                $Client->app->onOpen($SocketID);
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
        if ($this->maxPerIP > 0 && $this->Clients[$SocketID]->clientType == 'websocket') {
            $ip = $this->Clients[$SocketID]->ip;
            $this->clientIPs[$ip]->count--;
            if ($this->clientIPs[$ip]->count <= 0) {
                unset($this->clientIPs[$ip]);
            }
        }
        unset($this->Clients[$SocketID]);
        unset($this->Sockets[$SocketID]);
        return $SocketID;
    }

    private function extractMessage($SocketID, $messageFrame) {
        $client = $this->Clients[$SocketID];

        $message = $this->Decode($messageFrame);
        if ($this->opcode == 10) { //pong
            if ($client->expectPong == false) {
                $this->log("Unsolicited Pong frame received from socket #$SocketID"); // just ignore
            } else {
                $this->log("Expected Pong frame received from socket #$SocketID"); // just ignore
                $client->expectPong = false;
            }
            return '';
        }
        if ($this->opcode == 9) { //ping received
            $this->log("Ping frame received from socket #$SocketID");
            $messageFrame[0] = 138; // send back as pong
            fwrite($this->Sockets[$SocketID], $messageFrame, strlen($messageFrame));
            return '';
        }
        if ($this->opcode == 8) { //Connection Close Frame 
            $this->log("Connection Close frame received from socket #$SocketID");
            $this->Close($SocketID);
            return '';
        }
        if ($this->fin == 0 && $this->opcode == 0) {
            $this->Clients[$SocketID]->fin = false; // fragmented message
        } else if ($this->fin != 0 && $this->opcode != 0) {
            $this->Clients[$SocketID]->fin = true;
        }


        $this->Write($SocketID, json_encode((object) [
                            'opcode' => 'next',
                            'fyi' => $this->Clients[$SocketID]->fyi]));
        /*
         * ***********************************************
         * take car of buffering messages either because
         * buffrerON===true or fin===false
         * ***********************************************
         */
        if ($this->serverCommand($client, $message)) {
            return '';
        }

        if ($client->bufferON) {
            if (count($client->buffer) <= $this->maxChunks) {
                $client->buffer[] = $message;
            } else {
                $this->log("Too many chunks from socket #$SocketID");
                $this->onCLose($SocketID);
            }
            return '';
        }
        return $message;
    }

    public final function Write($SocketID, $message) {

        $message = $this->Encode($message);

        return fwrite($this->Sockets[$SocketID], $message, strlen($message));
    }

    public final function feedback($packet) {
        foreach ($this->Clients as $client) {
            if ($packet->uuid == $client->uuid && $client->clientType === 'websocket') {
                $this->Write($client->ID, json_encode($packet));
                return;
            }
        }
    }

    public final function broadCast($SocketID, $M) {
        $ME = $this->Encode($M);
        foreach ($this->Clients as &$client) {
            if ($client->clientType === 'websocket') {
                if ($SocketID == $client->ID) {
                    continue;
                }
                fwrite($this->Sockets[$client->ID], $ME, strlen($ME));
            }
        }
        return;
    }

    public final function pingClients() {

        $this->opcode = 9; // PING
        $m = $this->Encode(json_encode((object) ['opcode' => 'PING']));
        $nw = false;
        foreach ($this->Clients as &$client) {
            if ($client->clientType === 'websocket') {
                fwrite($this->Sockets[$client->ID], $m, strlen($m));
                $client->expectPong = true;
                $nw = true;
            }
        }
        return $nw;
    }

    public final function registerResource($name, $app) {
        $this->allApps[$name] = $app;
        foreach (['registerServer', 'onOpen', 'onData', 'onClose', 'onError'] as $method) {
            if (!method_exists($app, $method)) {
                $this->allApps[$name] = NULL;
                return false;
            }
        }
        $app->registerServer($this);
        return true;
    }

    private function specificChecks($SocketID) {

        $ok = true;
        $Client = $this->Clients[$SocketID];

        if ($Client->app === NULL) {
            $this->Log("Application incomplete or does not exist);"
                    . " Telling Client to disconnect on  #$SocketID");
            $msg = (object) ['opcode' => 'close'];
            $this->Write($SocketID, json_encode($msg));
            $this->Close($SocketID);
            $ok = false;
        }

        if ($this->maxPerIP > 0 && $this->Clients[$SocketID]->clientType == 'websocket') {
            /*
             * ***********************************************
             * track number of websocket connectins from this IP
             * ***********************************************
             */
            $ip = $Client->ip;
            if (!isset($this->clientIPs[$ip])) {
                $this->clientIPs[$ip] = (object) [
                            'SocketId' => $SocketID,
                            'count' => 1
                ];
            } else {
                $this->clientIPs[$ip]->count++;
                if ($this->clientIPs[$ip]->count > $this->maxPerIP) {
                    $msg = "To many connections from:  $ip";
                    $this->Log("$SocketID, $msg");
                    $this->Write($SocketID, json_encode((object) ['opcode' => 'close', 'error' => $msg]));
                    $this->Close($SocketID);
                    $ok = false;
                }
            }
        } else if (count($this->allowedIP) > 0 && $this->Clients[$SocketID]->clientType != 'websocket') {
            /*
             * ***********************************************
             * check if tcp client connects from allowed host
             * ***********************************************
             */
            if (!in_array($Client->ip, $this->allowedIP)) {
                $this->Close($SocketID);
                $this->Log("$SocketID, No connection allowed from: " . $Client->ip);
                $ok = false;
            }
        }
        return $ok;
    }

    private function serverCommand($client, &$message) {
        if ($client->fin === true) {
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
                return false;
            }
        }
        if ($client->bufferON === false) {
            if ($client->fin === false && count($client->buffer) == 0) {
                $this->Log("FIN=false ");
                $client->buffer[] = $message; // a fragement
                return true;
            }
            if ($client->fin === true && count($client->buffer) > 0) {
                $client->buffer[] = $message; // last fragement
                $message = implode('', $client->buffer);
                $client->buffer = [];
                $this->Log('FIN=true');
            }
        }

        return false;
    }

    public final function Log($m) {
        if ($this->logging) {
            $this->logging->log($m);
        }
    }

    public function guidv4() {
// from https://www.uuidgenerator.net/dev-corner/php
// Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $data = random_bytes(16);
        assert(strlen($data) == 16);

// Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
// Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

// Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    function onClose($SocketID) { // ...socket has been closed AND deleted
        $this->Log("Connection closed to socket #$SocketID");
        if ($this->Clients[$SocketID]->app == NULL) {
            return;
        }
        if (method_exists($this->Clients[$SocketID]->app, 'onClose')) {
            $this->Clients[$SocketID]->app->onClose($SocketID);
        }
    }

    function onError($SocketID, $message) { // ...any connection-releated error
        $this->Log("Socket $SocketID - " . $message);
        if ($this->Clients[$SocketID]->app == NULL) {
            return;
        }
        if (method_exists($this->Clients[$SocketID]->app, 'onError')) {
            $this->Clients[$SocketID]->app->onError($SocketID, $message);
        }
    }

}
