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
            $serverSecret,
            $running = true;

    function __construct($Address, $logger, $certFile = '', $pkFile = '') {

        $errno = 0;
        $errstr = '';
        $this->logging = $logger;
        $this->token = bin2hex(random_bytes(8));
        $this->serverSecret = random_bytes(32); // Cryptographic secret for HMAC checks

        $context = stream_context_create();
        $Port = '';

        // Determine if direct SSL termination by PHP is enabled and valid cert files exist
        if ($this->isSecure($Address, $Port) && !empty($certFile) && !empty($pkFile) && file_exists($certFile) && file_exists($pkFile)) {
                        
            $develop = GetAllConfig::load()['develop']['developsystem'] ?? false;
            // Configure TLS context options for direct PHP SSL handling
            stream_context_set_option($context, 'ssl', 'local_cert', $certFile);
            stream_context_set_option($context, 'ssl', 'local_pk', $pkFile);
            stream_context_set_option($context, 'ssl', 'verify_peer', !$develop);
            stream_context_set_option($context, 'ssl', 'verify_peer_name', !$develop);
            stream_context_set_option($context, 'ssl', 'allow_self_signed', $develop);
            stream_context_set_option($context, 'ssl', 'crypto_method', STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER);

            $this->isSSL = true;
        } else {
            // Pass-Through Mode: PHP operates over plain TCP (Reverse Proxy like Nginx handles SSL)
            $this->isSSL = false;
        }

        // Always bind stream_socket_server over tcp:// stream transport regardless of TLS mode
        $usingSSL = 'tcp://';
        $socket = stream_socket_server("$usingSSL$Address:$Port", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

        $modeNotice = $this->isSSL ? "Direct PHP TLS Enabled" : "Pass-Through / Plain TCP Mode";
        $this->Log("Server initialized on " . PHP_OS . " ($modeNotice) at $Address:$Port");

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

        // Enable graceful shutdown OS signals (Linux/Unix CLI)
        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, [$this, 'stop']);
            pcntl_signal(SIGTERM, [$this, 'stop']);
        }
    }

    public function stop() {
        $this->Log("Shutting down server gracefully...");
        $this->running = false;
        foreach ($this->Sockets as $socket) {
            $this->Close($socket);
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

    private function extractIPort($ipport) {
        if (empty($ipport)) {
            return (object) ['ip' => '0.0.0.0', 'port' => 0];
        }
        $parts = explode(':', $ipport);
        $port = array_pop($parts);
        $ip = trim(implode(':', $parts), '[]');
        return (object) ['ip' => $ip, 'port' => $port];
    }

    public function Start() {

        $this->Log("Starting server...");
        foreach ($this->allApps as $appName => $class) {
            $this->Log("Registered resource : $appName");
        }

        $pendingSSL = []; // Tracks pending TLS negotiations: [SocketID => startTime]
        $startTime = time();

        while ($this->running) {
            $socketArrayRead = $this->Sockets;
            $socketArrayWrite = NULL;
            $socketArrayExceptions = NULL;

            $ncon = @stream_select($socketArrayRead, $socketArrayWrite, $socketArrayExceptions, 1, 0);

            if ($ncon === false) {
                // Interrupted by signal or system event
                continue;
            }

            // 1. TIMEOUT CHECK FOR STALLED / SLOW-CLIENT TLS HANDSHAKES
            $currentTime = time();
            foreach ($pendingSSL as $pSocketID => $pStartTime) {
                if ($currentTime - $pStartTime > 3) { // 3-second grace period
                    $this->Log("Dropping socket #$pSocketID: TLS handshake timeout.");
                    if (isset($this->Sockets[$pSocketID])) {
                        @fclose($this->Sockets[$pSocketID]);
                    }
                    unset($this->Sockets[$pSocketID], $this->Clients[$pSocketID], $pendingSSL[$pSocketID]);
                }
            }

            // Handle idle ping interval
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

                // 2. ACCEPT NEW INCOMING TCP CONNECTIONS
                if ($Socket === $this->socketMaster) {
                    $clientSocket = @stream_socket_accept($Socket);
                    if (!is_resource($clientSocket)) {
                        $this->Log("Connection could not be established");
                        continue;
                    }

                    stream_set_blocking($clientSocket, false);
                    $SocketID = intval($clientSocket);
                    $ipport = stream_socket_get_name($clientSocket, true);
                    $ip = $this->extractIPort($ipport);

                    // Initialize client metadata container right away
                    $this->Clients[$SocketID] = (object) [
                                'ID' => $SocketID,
                                'uuid' => '',
                                'clientType' => null,
                                'Handshake' => false,
                                'handshakeBuffer' => '',
                                'timeCreated' => time(),
                                'app' => NULL,
                                'ip' => $ip->ip ?? '0.0.0.0',
                                'fyi' => '',
                                'ident' => '',
                                'allowremote' => 'no',
                                'expectPong' => false,
                                'headers' => []
                    ];
                    $this->Sockets[$SocketID] = $clientSocket;

                    if ($this->isSSL) {
                        $pendingSSL[$SocketID] = time(); // Register for non-blocking TLS negotiation
                    }

                    $this->Log("New client connecting from $ipport on socket #$SocketID");
                    continue;
                }

                // 3. NEGOTIATE NON-BLOCKING TLS HANDSHAKE
                if (isset($pendingSSL[$SocketID])) {
                    $cryptoResult = @stream_socket_enable_crypto(
                                    $this->Sockets[$SocketID],
                                    true,
                                    STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER
                            );

                    if ($cryptoResult === true) {
                        $this->Log("SSL/TLS Handshake successful on socket #$SocketID");
                        unset($pendingSSL[$SocketID]);
                        continue;
                    } elseif ($cryptoResult === false) {
                        $this->Log("SSL/TLS Handshake failed on socket #$SocketID");
                        @fclose($this->Sockets[$SocketID]);
                        unset($this->Sockets[$SocketID], $this->Clients[$SocketID], $pendingSSL[$SocketID]);
                        continue;
                    } else {
                        continue;
                    }
                }

                // 4. READ & PROCESS WEBSOCKET / HTTP HANDSHAKE DATA
                if (!isset($this->Clients[$SocketID])) {
                    continue;
                }

                $Client = $this->Clients[$SocketID];

                if ($Client->Handshake) {
                    $frames = $this->readDecode($SocketID);

                    foreach ($frames as $frame) {
                        $opcode = $frame['opcode'];
                        $message = $frame['data'];

                        if ($this->extractMessage($SocketID, $opcode, $message) === false) {
                            continue;
                        }

                        if (($opcode === 1 || $opcode === 2) && $Client->app !== NULL) {
                            $Client->app->onData($SocketID, $message);
                            $this->Write($SocketID, json_encode((object) [
                                                'opcode' => 'next',
                                                'fyi' => $Client->fyi
                            ]));
                        }
                    }
                    continue;
                }

                // HTTP WebSocket Handshake reading
                $dataBuffer = fread($Socket, $this->bufferLength);

                if ($dataBuffer === false || strlen($dataBuffer) === 0) {
                    $this->onError($SocketID, "Client disconnected by Server - TCP connection lost");
                    $this->Close($Socket);
                    continue;
                }

                // Accumulate buffer to support non-blocking fragmented HTTP requests
                $Client->handshakeBuffer .= $dataBuffer;

                if (strlen($Client->handshakeBuffer) >= $this->bufferChunk) {
                    $this->onError($SocketID, "Client payload chunk limit exceeded");
                    $this->Close($Socket);
                    continue;
                }

                // Wait until full HTTP headers (\r\n\r\n) are available before executing Handshake
                if (strpos($Client->handshakeBuffer, "\r\n\r\n") === false) {
                    continue;
                }

                if ($this->Handshake($Socket, $Client->handshakeBuffer) === false) {
                    $this->onError($SocketID, "Client handshake false\r $Client->handshakeBuffer");
                        //  $this->onError($SocketID, "Client handshake false");
              
                    $this->Close($Socket);
                    continue;
                }

                // Resolve real IP now that headers are fully parsed
                $Client->ip = $this->getRealClientIP($SocketID);

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
            $Socket = $this->Sockets[$Socket] ?? null;
        }

        if (!$Socket) {
            return false;
        }

        if (is_resource($Socket)) {
            @stream_socket_shutdown($Socket, STREAM_SHUT_RDWR);
            @fclose($Socket);
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

        $totalBytes = strlen($m);
        $writtenBytes = 0;

        while ($writtenBytes < $totalBytes) {
            $result = @fwrite($this->Sockets[$SocketID], substr($m, $writtenBytes));
            if ($result === false || $result === 0) {
                // Fixed: Disconnect clients on mid-write failure to prevent stream corruption
                $this->Log("Write error on socket #$SocketID - Closing broken socket");
                $this->Close($SocketID);
                return false;
            }
            $writtenBytes += $result;
        }

        return $writtenBytes;
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
        foreach ($this->Clients as &$client) {
            if ($client->clientType === 'websocket') {
                if ($SocketID == $client->ID) {
                    continue;
                }
                if (isset($this->Sockets[$client->ID])) {
                    $this->Write($client->ID, $M);
                }
            }
        }
        return;
    }

    public final function pingClients() {
        $this->opcode = 9;
        $m = json_encode((object) ['opcode' => 'PING']);
        $nw = false;

        foreach ($this->Clients as $SocketID => &$client) {
            if ($client->clientType === 'websocket') {
                // Fixed: Evict dead connections that failed to answer the previous PING
                if ($client->expectPong === true) {
                    $this->Log("Socket #$SocketID timed out (No PONG received). Closing.");
                    $this->Close($SocketID);
                    continue;
                }

                if (isset($this->Sockets[$client->ID])) {
                    $this->Write($client->ID, $m);
                    $client->expectPong = true;
                    $nw = true;
                }
            }
        }
        $this->opcode = 1;

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

    public function getRealClientIP($SocketID) {
        if (!isset($this->Clients[$SocketID])) {
            return '0.0.0.0';
        }

        $Client = $this->Clients[$SocketID];

        if (!empty($Client->headers['x-forwarded-for'])) {
            $forwardedIps = explode(',', $Client->headers['x-forwarded-for']);
            return trim($forwardedIps[0]);
        }

        return $Client->ip;
    }

    private function specificChecks($SocketID) {
        $Client = $this->Clients[$SocketID];

        if (!empty($this->allowedOrigins)) {
            $origin = $Client->headers['origin'] ?? null;
            if ($origin === null || !in_array($origin, $this->allowedOrigins, true)) {
                $this->Log("$SocketID, Rejected missing or invalid origin: " . ($origin ?? 'NONE'));
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
