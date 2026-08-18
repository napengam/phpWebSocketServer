<?php

class websocketCore {

    public $prot, $connected = false, $firstFragment = true, $finBit = true,
            $ident, $socketMaster, $key, $expectedToken, $errorHandshake, $fin, $opcode,
            $frame, $length, $fromUUID, $timeout = 2;
    // Security limit: Max 10MB payload size
    private int $maxPayloadSize = 10485760;

    function __construct($Address, $ident = '') {
        $this->ident = $ident;

        // Extract protocol and set default port
        $parts = explode('://', $Address, 2);
        $protocol = count($parts) > 1 ? strtolower($parts[0]) : 'tcp';
        $Address = count($parts) > 1 ? $parts[1] : $Address;

        $isSecure = ($protocol === 'ssl' || $protocol === 'wss');
        $defaultPort = $isSecure ? '443' : '80';
        $prot = $isSecure ? 'ssl://' : 'tcp://';

        // Extract endpoint and default to '/'
        [$host, $app] = explode('/', $Address, 2) + [null, '/'];
        $app = '/' . $app;

        // Extract port if specified
        [$host, $port] = explode(':', $host, 2) + [null, $defaultPort];

        // Security Fix #4: Explicit TLS context configuration
        $sslConfig = require __DIR__ . '/config.php';
        $contextOptions = [];
        if ($isSecure) {
            $contextOptions['ssl'] = [
                'verify_peer' => $sslConfig['ssl']['verify_peer'],
                'verify_peer_name' => $sslConfig['ssl']['verify_peer_name'],
                'allow_self_signed' => $sslConfig['ssl']['allow_self_signed'],
                'peer_name' => $host,
            ];

            if (!empty($sslConfig['ssl']['cafile'])) {
                $contextOptions['ssl']['cafile'] = $sslConfig['ssl']['cafile'];
            }
        }
        $context = stream_context_create($contextOptions);

        $errno = 0;
        $errstr = '';
        $this->socketMaster = @stream_socket_client("$prot$host:$port", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);

        if (!$this->socketMaster) {
            return $this->connected = false;
        }

        $this->connected = true;
        $this->prot = $prot;
        fwrite($this->socketMaster, $this->setHandshake($host, $app));

        if (!$this->getHandshake(fread($this->socketMaster, 1024))) {
            $this->silent();
            echo $this->errorHandshake;
            return false;
        }

        stream_set_timeout($this->socketMaster, $this->timeout);
        return true;
    }

    final function writeSocket($message) {
        if ($this->connected) {
            fwrite($this->socketMaster, $this->encodeForServer($message));
        }
    }

    final function readSocket() {
        if (!$this->connected) {
            return '';
        }
        $buff = [];
        $i = 0;
        do {
            $continue = false;
            $buff[$i] = $this->decodeFromServer(fread($this->socketMaster, 8192));
            if (stream_get_meta_data($this->socketMaster)['timed_out']) {
                $this->connected = false;
                return '';
            }
            switch ($this->opcode) {
                case 9: // Ping frame
                    $this->opcode = 10;
                    $m = implode('', $buff);
                    $this->writeSocket($m);
                    $this->fin = false;
                    $continue = true;
                    break;

                case 10: // Pong frame
                    $this->fin = false;
                    $continue = true;
                    break;

                case 8: // Close frame
                    $this->silent();
                    return '';

                default:
                    $this->length -= strlen($buff[$i]);
                    break;
            }
            if ($continue) {
                continue;
            }
            $i++;
            while ($this->length > 0) {
                $chunk = fread($this->socketMaster, min(8192, $this->length));
                if ($chunk === false || stream_get_meta_data($this->socketMaster)['timed_out']) {
                    $this->connected = false;
                    return '';
                }
                $buff[$i] = $chunk;
                $this->length -= strlen($chunk);
                $i++;
            }
        } while ($this->fin === false);
        return implode('', $buff);
    }

    final function silent() {
        if ($this->connected) {
            $this->writeSocket(''); // close
            fclose($this->socketMaster);
            $this->connected = false;
        }
    }

    private function setHandshake($server, $app = '/') {
        // Security Fix #1: Prevent CRLF / Header Injection
        $server = str_replace(["\r", "\n"], '', $server);
        $app = str_replace(["\r", "\n"], '', $app);

        $app = '/' . ltrim($app, '/');
        $this->key = random_bytes(16);
        $key = base64_encode($this->key);

        $this->expectedToken = base64_encode(sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));
        $prot = ($this->prot === 'ssl://') ? "https://" : "http://";

        $req = [
            "GET $app HTTP/1.1",
            "Host: $server",
            "Upgrade: websocket",
            "Connection: Upgrade",
            "Sec-WebSocket-Key: $key",
            "Origin: {$prot}{$server}",
            "Sec-WebSocket-Version: 13",
            "Client-Type: php", // Private, not part of RFC6455
            "Ident: $this->ident", // Private, not part of RFC6455
            "allowRemote: ''"               // Private, not part of RFC6455
        ];

        return implode("\r\n", $req) . "\r\n\r\n";
    }

    private function getHandshake($Buffer) {
        if (!$Buffer) {
            return false;
        }
        $this->errorHandshake = $Buffer;

        if (stripos($Buffer, "HTTP/1.1 101") === false) {
            return false;
        }

        $Headers = [];
        foreach (explode("\n", $Buffer) as $Line) {
            if (strpos($Line, ":") !== false) {
                [$k, $v] = explode(":", $Line, 2);
                $Headers[strtolower(trim($k))] = trim($v);
            }
        }

        if (
                empty($Headers['upgrade']) || strcasecmp($Headers['upgrade'], 'websocket') !== 0 ||
                empty($Headers['connection']) || strcasecmp($Headers['connection'], 'Upgrade') !== 0 ||
                empty($Headers['sec-websocket-accept']) || $Headers['sec-websocket-accept'] !== $this->expectedToken
        ) {
            return false;
        }

        $this->errorHandshake = '';
        return true;
    }

    final function encodeForServer($M) {
        $L = strlen($M);

        if ($L === 0) {
            $firstByte = 136; // Close frame (0x88)
        } else {
            if ($this->opcode === 10) {
                $firstByte = 138; // Pong frame (0x8A)
            } elseif ($this->finBit) {
                $firstByte = $this->firstFragment ? 129 : 128;
                $this->firstFragment = true;
            } else {
                $firstByte = $this->firstFragment ? 1 : 0;
                $this->firstFragment = false;
            }
        }

        if ($L <= 125) {
            $header = pack('CC', $firstByte, $L | 128);
        } elseif ($L <= 65535) {
            $header = pack('CCn', $firstByte, 126 | 128, $L);
        } else {
            $header = pack('CCJ', $firstByte, 127 | 128, $L);
        }

        $masks = random_bytes(4);
        return $header . $masks . ($M ^ str_pad('', $L, $masks));
    }

    final function decodeFromServer($frame) {
        if (!$frame || strlen($frame) < 2) {
            $this->opcode = 8; // force close connection
            $this->fin = true;
            $this->length = 0;
            $this->frame = '';
            return '';
        }

        $b1 = ord($frame[0]);
        $this->fin = ($b1 & 0x80) !== 0;
        $this->opcode = $b1 & 0x0F;
        $this->frame = $frame;

        $length = ord($frame[1]) & 0x7F;
        $poff = 2;

        // Security Fix #3: Check buffer length before reading extended payload offsets
        if ($length === 126) {
            if (strlen($frame) < 4) {
                return '';
            }
            $length = unpack('n', substr($frame, 2, 2))[1];
            $poff = 4;
        } elseif ($length === 127) {
            if (strlen($frame) < 10) {
                return '';
            }
            $length = unpack('J', substr($frame, 2, 8))[1];
            $poff = 10;
        }

        // Security Fix #2: Max payload limit check against DoS
        if ($length > $this->maxPayloadSize) {
            $this->silent();
            return '';
        }

        $this->length = $length;
        return substr($frame, $poff, $length);
    }
}
