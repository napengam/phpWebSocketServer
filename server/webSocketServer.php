<?php

require __DIR__ . '/RFC6455.php';

class WebSocketServer {

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
            $maxChunks = 100,
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
        $this->Sockets[intval($socket)] = $socket;
        $this->socketMaster = $socket;

        error_reporting($this->errorReport);
        set_time_limit($this->timeLimit);
        if ($this->implicitFlush) {
            ob_implicit_flush();
        }
    }

    private function isSecure(&$Address) {
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
        $socketArrayWrite = $socketArrayExceptions = NULL;
        while ($a) {
            $socketArrayRead = $this->Sockets;
            stream_select($socketArrayRead, $socketArrayWrite, $socketArrayExceptions, 0, 200);
            foreach ($socketArrayRead as $Socket) {
                $SocketID = intval($Socket);
                if ($Socket === $this->socketMaster) {
                    /*
                     * ***********************************************
                     * new client
                     * ***********************************************
                     */
                    $clientSocket = stream_socket_accept($Socket);
                    /*
                     * ***********************************************
                     * get IP:Port of client
                     * ***********************************************
                     */
                    $ipport = stream_socket_get_name($clientSocket, true);
                    $ip = $this->extractIP($ipport); // can be ipv4 or ipv6

                    if (!is_resource($clientSocket)) {
                        $this->Log("$SocketID, Connection could not be established");
                        continue;
                    } else {
                        $this->Log("Connecting from IP: $ip");
                        $SocketID = intval($clientSocket);
                        $this->Clients[$SocketID] = (object) [
                                    'ID' => $SocketID,
                                    'uuid' => '',
                                    'Headers' => null,
                                    'Handshake' => null,
                                    'timeCreated' => null,
                                    'bufferON' => false,
                                    'buffer' => [],
                                    'app' => NULL,
                                    'ip' => $ip
                        ];
                        $this->Sockets[$SocketID] = $clientSocket;

                        $this->Log("New client connecting from $ipport on socket #$SocketID");
                    }
                    continue;
                }
                /*
                 * ***********************************************
                 * read data from socket and check
                 * ***********************************************
                 */
                $dataBuffer = fread($Socket, $this->bufferLength);
                if ($dataBuffer === false ||
                        strlen($dataBuffer) == 0 || // use mb_strlen isntead of strlen ???
                        strlen($dataBuffer) >= $this->bufferChunk) {  // to avoid malicious overload 
                    $this->onError($SocketID, "Client disconnected by Server - TCP connection lost");
                    $this->Close($Socket);
                    continue;
                }

                $Client = $this->Clients[$SocketID];
                if ($Client->Handshake == false) {
                    /*
                     * ***********************************************
                     * handshake
                     * ***********************************************
                     */
                    if ($this->Handshake($Socket, $dataBuffer)) {
                        if ($Client->app === NULL) {
                            $this->Log("Application incomplete or does not exist);"
                                    . " Telling Client to disconnect on  #$SocketID");
                            $msg = (object) Array('opcode' => 'close', 'os' => $this->serveros);
                            $this->Write($SocketID, json_encode($msg));
                            $this->Close($Socket);
                        } else {
                            if ($this->maxPerIP > 0 && $this->Clients[$SocketID]->Headers == 'websocket') {
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
                                        $this->Close($SocketID);
                                        $this->Log("$SocketID, To many connections from: " . $ip);
                                        continue;
                                    }
                                }
                            } else if (count($this->allowedIP) > 0) {
                                /*
                                 * ***********************************************
                                 * check if tcp client connects from allowed host
                                 * ***********************************************
                                 */
                                if (!is_set($this->allowedIP[$Client->ip])) {
                                    $this->Close($SocketID);
                                    $this->Log("$SocketID, No connection allowed from: " . $Client->ip);
                                    continue;
                                }
                            }

                            $this->Log("Telling Client to start on  #$SocketID");
                            $uuid = $this->guidv4();
                            $msg = (object) ['opcode' => 'ready', 'uuid' => $uuid];
                            $this->Clients[$SocketID]->uuid = $uuid;
                            $this->Write($SocketID, json_encode($msg));
                            $Client->app->onOpen($SocketID);
                        }
                    }
                    continue;
                }
                /*
                 * ***********************************************
                 * message from client
                 * ***********************************************
                 */
                $message = $this->Read($SocketID, $dataBuffer);
                if ($message != '') {
                    /*
                     * ***********************************************
                     * route message to application class 
                     * ***********************************************
                     */
                    $Client->app->onData($SocketID, $message);
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
        if ($this->maxPerIP > 0 && $this->Clients[$SocketID]->Headers == 'websocket') {
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

    private function Read($SocketID, $message) {
        $client = $this->Clients[$SocketID];
        if ($client->Headers === 'websocket') {
            $message = $this->Decode($message);
            if ($this->opcode == 10) { //pong
                $this->log("Unsolicited Pong frame received from socket #$SocketID"); // just ignore
                return '';
            }
            if ($this->opcode == 8) { //Connection Close Frame 
                $this->log("Connection Close frame received from socket #$SocketID");
                $this->Close($SocketID);
                return '';
            }
        }

        $this->Write($SocketID, json_encode((object) ['opcode' => 'next']));
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
        if ($this->Clients[$SocketID]->Headers === 'websocket') {
            $message = $this->Encode($message);
        }
        return fwrite($this->Sockets[$SocketID], $message, strlen($message));
    }

    public final function feedback($packet) {
        foreach ($this->Clients as $client) {
            if ($packet->uuid == $client->uuid && $client->Headers === 'websocket') {
                $this->Write($client->ID, json_encode($packet));
                return;
            }
        }
    }

    public final function broadCast($SocketID, $M) {
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

    public final function Log($m) {
        if ($this->logging) {
            $this->logging->log($m);
        }
    }

    public function guidv4($data = null) {
        // from https://www.uuidgenerator.net/dev-corner/php
        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $data = $data ?? random_bytes(16);
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
