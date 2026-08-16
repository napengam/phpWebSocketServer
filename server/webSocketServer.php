<?php

require __DIR__ . '/RFC_6455.php';

class webSocketServer {

    use RFC_6455; // TRAIT to implement methods required by RFC6455

    public
            $logging = '',
            $Sockets = [],
            $bufferLength = 40960,
            $bufferChunk = 8192,
            $maxBufferSize = 5242880, // Maximum total buffer limit (5 MB) per client
            $errorReport = E_ALL,
            $timeLimit = 0,
            $implicitFlush = true,
            $Clients = [],
            $clientIPs = [],
            $maxPerIP = 0, // 0 = unlimited
            $allowedIP = [],
            $allowedOrigins = [], // e.g., ['https://example.com']
            $opcode = 1,
            $pingInterval = 0,
            $maxChunks = 100,
            $maxClients = 0,
            $fin;
    protected
            $isSSL = false,
            $token,
            $Address,
            $Port,
            $socketMaster,
            $allApps = [],
            $serverSecret;

    function __construct($Address, $logger, $certFile = '', $pkFile = '') {

        $errno = 0;
        $errstr = '';
        $this->logging = $logger;
        $this->token = bin2hex(random_bytes(8));
        $this->serverSecret = random_bytes(32); // Cryptographic secret for HMAC checks

        $usingSSL = 'tcp://';
        $context = stream_context_create();
        $Port = '';

        if ($this->isSecure($Address, $Port)) {
            $develop = GetAllConfig::load()['develop']['developsystem'];
            // Security Fix: Enable verification and strict TLS options
            stream_context_set_option($context, 'ssl', 'local_cert', $certFile);
            stream_context_set_option($context, 'ssl', 'local_pk', $pkFile);
            stream_context_set_option($context, 'ssl', 'verify_peer', !$develop);
            stream_context_set_option($context, 'ssl', 'verify_peer_name', !$develop);
            stream_context_set_option($context, 'ssl', 'allow_self_signed', $develop);
            $usingSSL = "tcp://";
            $this->isSSL = true;
        }

        $socket = stream_socket_server("$usingSSL$Address:$Port", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

        $this->Log("Server initialized on " . PHP_OS . "  $Address:$Port $usingSSL");
        if (!$socket) {
            $this->Log("Error $errno creating stream: $errstr", true);
            openlog('websock', LOG_PID, LOG_USER);
            syslog(LOG_ERR, "Error $errno creating stream: $errstr with $usingSSL$Address:$Port");
            closelog();
            exit;
        }

        $this->Sockets[intval($socket)] = $socket;
        $this->socketMaster = $socket;

        $resolvedIp = gethostbyname($Address);
        if ($resolvedIp !== false) {
            $this->allowedIP[] = $resolvedIp;
        }
        $this->allowedIP[] = '::1';

        error_reporting($this->errorReport);
        set_time_limit($this->timeLimit);
        if ($this->implicitFlush) {
            ob_implicit_flush();
        }
    }

    private function isSecure(&$Address, &$port) {
        $secure = false;
        $arr = explode('://', $Address);

        if (count($arr) > 1) {
            if (strncasecmp($arr[0], 'ssl', 3) === 0 || strncasecmp($arr[0], 'wss', 3) === 0) {
                $Address = $arr[1];
                $secure = true;
                $port = '443';
            } else {
                $Address = $arr[1];
                $port = '80';
            }
        }

        $arr = explode(':', $Address);
        if (count($arr) > 1) {
            $Address = $arr[0];
            $port = $arr[1];
        }

        return $secure;
    }

    public function Start() {

        $this->Log("Starting server...");
        foreach ($this->allApps as $appName => $class) {
            $this->Log("Registered resource : $appName");
        }

        $a = true;
        $socketArrayWrite = $socketArrayExceptions = NULL;
        $startTime = time();

        while ($a) {
            $socketArrayRead = $this->Sockets;
            $ncon = stream_select($socketArrayRead, $socketArrayWrite, $socketArrayExceptions, 1, 0);

            if ($ncon === 0) {
                if ($this->pingInterval > 0 && (time() - $startTime) > $this->pingInterval) {
                    if ($this->pingClients()) {
                        $this->Log("Ping Clients");
                    }
                    $startTime = time();
                }
                continue;
            }

            foreach ($socketArrayRead as $Socket) {
                $SocketID = intval($Socket);

                if ($Socket === $this->socketMaster) {
                    $clientSocket = stream_socket_accept($Socket);
                    if (!is_resource($clientSocket)) {
                        $this->Log("$SocketID, Connection could not be established");
                        continue;
                    }
                    // =========================================================
                    // OPTIONAL SSL ENCRYPTION HANDSHAKE
                    // =========================================================
                    if ($this->isSSL) {
                        stream_set_blocking($clientSocket, true); // Must be blocking for handshake

                        $cryptoResult = @stream_socket_enable_crypto(
                                        $clientSocket,
                                        true,
                                        STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER
                                );

                        if ($cryptoResult !== true) {
                            $this->Log("SSL/TLS Handshake failed on client socket.");
                            @fclose($clientSocket);
                            continue;
                        }

                        stream_set_blocking($clientSocket, false); // Return to non-blocking for event loop
                    }
                    $ipport = stream_socket_get_name($clientSocket, true);
                    $ip = $this->extractIPort($ipport);
                    $this->Log("Connecting from IP: $ip->ip");
                    $SocketID = intval($clientSocket);

                    $this->Clients[$SocketID] = (object) [
                                'ID' => $SocketID,
                                'uuid' => '',
                                'clientType' => null,
                                'Handshake' => false,
                                'timeCreated' => time(),
                                'app' => NULL,
                                'ip' => $ip->ip,
                                'fyi' => '',
                                'ident' => '',
                                'allowremote' => 'no',
                                'expectPong' => false
                    ];

                    $this->Sockets[$SocketID] = $clientSocket;
                    $this->Log("New client connecting from $ipport on socket #$SocketID\r\n");
                    continue;
                }

                $Client = $this->Clients[$SocketID];

                if ($Client->Handshake) {
                    $frames = $this->readDecode($SocketID);

                    foreach ($frames as $frame) {
                        $opcode = $frame['opcode'];
                        $message = $frame['data'];

                        // Extract internal commands (Ping, Pong, Close)
                        if ($this->extractMessage($SocketID, $opcode, $message) === false) {
                            continue;
                        }

                        // Fully reassembled data frame dispatch
                        if (($opcode === 1 || $opcode === 2) && $Client->app !== NULL) {
                            $Client->app->onData($SocketID, $message);
                            // Send 'next' acknowledgment frame back to the client
                            $this->Write($SocketID, json_encode((object) [
                                                'opcode' => 'next',
                                                'fyi' => $Client->fyi
                            ]));
                        }
                    }

                    continue;
                }

                $dataBuffer = fread($Socket, $this->bufferLength);

                if ($dataBuffer === false || strlen($dataBuffer) === 0 || strlen($dataBuffer) >= $this->bufferChunk) {
                    $this->onError($SocketID, "Client disconnected by Server - TCP connection lost or chunk limit exceeded");
                    $this->Close($Socket);
                    continue;
                }

                if ($this->Handshake($Socket, $dataBuffer) === false) {
                    continue;
                }

                if ($this->specificChecks($SocketID) === false) {
                    continue;
                }

                $this->Log("Telling Client to start on #$SocketID");
                $uuid = $this->guidv4();
                $msg = (object) ['opcode' => 'ready', 'uuid' => $uuid];
                $this->Clients[$SocketID]->uuid = $uuid;
                $this->Write($SocketID, json_encode($msg));

                if ($Client->app !== NULL) {
                    $Client->app->onOpen($SocketID);
                }
            }
        }
    }

    public function Close($Socket) {
        if (is_int($Socket)) {
            $Socket = $this->Sockets[$Socket];
        }

        if (is_resource($Socket)) {
            stream_socket_shutdown($Socket, STREAM_SHUT_RDWR);
        }

        $SocketID = intval($Socket);
        $this->onClose($SocketID);

        if (isset($this->Clients[$SocketID])) {
            if ($this->maxPerIP > 0 && $this->Clients[$SocketID]->clientType === 'websocket') {
                $ip = $this->Clients[$SocketID]->ip;
                if (isset($this->clientIPs[$ip])) {
                    $this->clientIPs[$ip]->count--;
                    if ($this->clientIPs[$ip]->count <= 0) {
                        unset($this->clientIPs[$ip]);
                    }
                }
            }
            unset($this->Clients[$SocketID]);
        }

        unset($this->Sockets[$SocketID]);
        return $SocketID;
    }

    private function extractMessage($SocketID, $opcode, $message) {
        $client = $this->Clients[$SocketID];
        $this->opcode = 1;

        if ($opcode === 10) { // Pong
            if ($client->expectPong === false) {
                $this->Log("Unsolicited Pong frame received from socket #$SocketID $message");
            } else {
                $this->Log("Expected Pong frame received from socket #$SocketID");
                $client->expectPong = false;
            }
            return false;
        }

        if ($opcode === 9) { // Ping
            $this->Log("Ping frame received from socket #$SocketID");
            $this->sendPong($SocketID, $message);
            return false;
        }

        if ($opcode === 8) { // Connection Close Frame
            $this->Log("Connection Close frame received from socket #$SocketID");
            $this->Close($SocketID);
            return false;
        }

        return true;
    }

    public final function Write($SocketID, $message) {
        if (!isset($this->Sockets[$SocketID]) || !is_resource($this->Sockets[$SocketID])) {
            return false;
        }
        $m = $this->Encode($message);
        return fwrite($this->Sockets[$SocketID], $m, strlen($m));
    }

    public final function feedback($packet) {
        foreach ($this->Clients as $client) {
            if (($packet->uuid == $client->uuid && $client->clientType === 'websocket') ||
                    ($packet->ident != '' && $packet->ident == $client->ident)) {
                $this->Write($client->ID, json_encode($packet));
                return;
            }
        }
    }

    public final function echo($sockid, $packet) {
        $this->Write($sockid, json_encode($packet));
    }

    public final function broadCast($SocketID, $M) {
        $ME = $this->Encode($M);
        foreach ($this->Clients as &$client) {
            if ($client->clientType === 'websocket') {
                if ($SocketID == $client->ID) {
                    continue;
                }
                if (isset($this->Sockets[$client->ID])) {
                    fwrite($this->Sockets[$client->ID], $ME, strlen($ME));
                }
            }
        }
        return;
    }

    public final function pingClients() {
        $this->opcode = 9;
        $m = $this->Encode(json_encode((object) ['opcode' => 'PING']));
        $this->opcode = 1;
        $nw = false;

        foreach ($this->Clients as &$client) {
            if ($client->clientType === 'websocket') {
                if (isset($this->Sockets[$client->ID])) {
                    fwrite($this->Sockets[$client->ID], $m, strlen($m));
                    $client->expectPong = true;
                    $nw = true;
                }
            }
        }

        return $nw;
    }

    public final function registerResource($name, $app) {
        $this->allApps[$name] = $app;
        foreach (['registerServerMethods', 'onOpen', 'onData', 'onClose', 'onError'] as $method) {
            if (!method_exists($app, $method)) {
                $this->allApps[$name] = NULL;
                return false;
            }
        }
        $app->registerServerMethods($this);
        return true;
    }

    private function specificChecks($SocketID) {
        $Client = $this->Clients[$SocketID];

        // CSWSH Protection Check
        if (!empty($this->allowedOrigins) && isset($_SERVER['HTTP_ORIGIN'])) {
            if (!in_array($_SERVER['HTTP_ORIGIN'], $this->allowedOrigins, true)) {
                $this->Log("$SocketID, Rejected origin: " . $_SERVER['HTTP_ORIGIN']);
                $this->Close($SocketID);
                return false;
            }
        }

        if ($Client->app === NULL) {
            $this->Log("Application incomplete or does not exist; Telling Client to disconnect on #$SocketID");
            $msg = (object) ['opcode' => 'close'];
            $this->Write($SocketID, json_encode($msg));
            $this->Close($SocketID);
            return false;
        }

        if ($this->maxClients > 0 && count($this->Clients) > $this->maxClients) {
            $msg = "Too many connections";
            $this->Log("$SocketID, $msg");
            $this->Write($SocketID, json_encode((object) ['opcode' => 'close', 'error' => $msg]));
            $this->Close($SocketID);
            return false;
        }

        if ($this->maxPerIP > 0 && $this->Clients[$SocketID]->clientType === 'websocket') {
            $ip = $Client->ip;
            if (!isset($this->clientIPs[$ip])) {
                $this->clientIPs[$ip] = (object) [
                            'SocketId' => $SocketID,
                            'count' => 1
                ];
            } else {
                $this->clientIPs[$ip]->count++;
                if ($this->clientIPs[$ip]->count > $this->maxPerIP) {
                    $msg = "Too many connections from: $ip";
                    $this->Log("$SocketID, $msg");
                    $this->Write($SocketID, json_encode((object) ['opcode' => 'close', 'error' => $msg]));
                    $this->Close($SocketID);
                    return false;
                }
            }
        } else if (count($this->allowedIP) > 0 && $this->Clients[$SocketID]->clientType !== 'websocket') {
            if (!in_array($Client->ip, $this->allowedIP, true)) {
                $this->Close($SocketID);
                $this->Log("$SocketID, No connection allowed from: " . $Client->ip);
                return false;
            }
        }

        return true;
    }

    public final function Log($m) {
        if ($this->logging) {
            $this->logging->log($m);
        }
    }

    public function guidv4() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        $hmac = hash_hmac('sha256', $uuid, $this->serverSecret);
        return $uuid . '.' . $hmac;
    }

    protected function verifyUUID($uuHash) {
        $parts = explode('.', $uuHash);
        if (count($parts) !== 2) {
            return false;
        }

        $uuid = $parts[0];
        $hmac = $parts[1];

        $expectedHmac = hash_hmac('sha256', $uuid, $this->serverSecret);
        return hash_equals($expectedHmac, $hmac);
    }

    function onClose($SocketID) {
        $this->Log("Connection closed to socket #$SocketID");
        if (!isset($this->Clients[$SocketID]) || $this->Clients[$SocketID]->app === NULL) {
            return;
        }
        if (method_exists($this->Clients[$SocketID]->app, 'onClose')) {
            $this->Clients[$SocketID]->app->onClose($SocketID);
        }
    }

    function onError($SocketID, $message) {
        $this->Log("Socket $SocketID - " . $message);
        if (!isset($this->Clients[$SocketID]) || $this->Clients[$SocketID]->app === NULL) {
            return;
        }
        if (method_exists($this->Clients[$SocketID]->app, 'onError')) {
            $this->Clients[$SocketID]->app->onError($SocketID, $message);
        }
    }
}
