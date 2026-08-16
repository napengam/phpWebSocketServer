<?php

class websocketCore {

    public $prot, $connected = false, $firstFragment = true, $finBit = true,
            $ident, $socketMaster, $key, $expectedToken, $errorHandshake, $fin, $opcode,
            $frame, $length, $fromUUID, $timeout = 2;
    private $buffer, $fragmentBuffer, $fragmentOpcode;

    function __construct($Address, $ident = '') {
        $this->ident = $ident;

        // Extract protocol and set default port
        $parts = explode('://', $Address, 2);
        $protocol = count($parts) > 1 ? strtolower($parts[0]) : 'tcp';
        $Address = count($parts) > 1 ? $parts[1] : $Address;

        $isSecure = ($protocol === 'ssl' || $protocol === 'wss');
        $defaultPort = $isSecure ? '443' : '80';
        $prot = $isSecure ? 'ssl://' : 'tcp://';

        if ($isSecure) {
            // PHP 8.x on Windows uses Windows Certificate Store by default
            // openssl.cafile empty = use system store automatically
            // No manual CA configuration needed
        }

        // Extract endpoint and default to '/'
        [$host, $app] = explode('/', $Address, 2) + [null, '/'];
        $app = '/' . $app;

        // Extract port if specified
        [$host, $port] = explode(':', $host, 2) + [null, $defaultPort];
        
        $errno = 0; $errstr = '';
        $this->socketMaster = @stream_socket_client("$prot$host:$port", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, stream_context_create());

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

        // Set a timeout for non-blocking client actions
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
        do { // probaly reading fragements
            $continue = false;
            $buff[$i] = $this->decodeFromServer(fread($this->socketMaster, 8192));
            if (stream_get_meta_data($this->socketMaster)['timed_out']) {
                $this->connected = false;
                return '';
            }
            switch ($this->opcode) {
                case 9: // Ping frame
                    $this->opcode = 10; // Respond with pong
                    $m = implode('', $buff);
                    $this->writeSocket($m);
                    $this->fin = false; // Continue reading
                    $continue = true;
                    break;

                case 10: // Pong frame
                    $this->fin = false; // Ignore, continue reading
                    $continue = true;
                    break;

                case 8: // Close frame
                    $this->silent(); // Close connection
                    return '';

                default:
                    // Adjust length remaining to read
                    $this->length -= strlen($buff[$i]);
                    break;
            }
            if ($continue) {
                continue;
            }
            $i++;
            while ($this->length > 0) { // data buffered by socket 
                $buff[$i] = fread($this->socketMaster, 8192);
                if (stream_get_meta_data($this->socketMaster)['timed_out']) {
                    $this->connected = false;
                    return '';
                }
                $this->length -= strlen($buff[$i]);
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
        // Normalize $app to always start with exactly one slash
        $app = '/' . ltrim($app, '/');
        $this->key = random_bytes(16);
        $key = base64_encode($this->key);

        // Expected token calculated from key and the WebSocket GUID using raw binary SHA1
        $this->expectedToken = base64_encode(sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));

        // Determine protocol based on $this->prot
        $prot = ($this->prot === 'ssl://') ? "https://" : "http://";

        // Assemble handshake request headers
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

        // Set the first byte based on opcode and fragment rules
        if ($L === 0) {
            $firstByte = 136; // Close frame (0x88)
        } else {
            if ($this->opcode === 10) {
                $firstByte = 138; // Pong frame (0x8A)
            } elseif ($this->finBit) {
                // FIN = 1
                $firstByte = $this->firstFragment ? 129 : 128; // 129: Complete text, 128: Final continuation
                $this->firstFragment = true; // Reset for the next message
            } else {
                // FIN = 0 (Fragmented message in progress)
                $firstByte = $this->firstFragment ? 1 : 0; // 1: First fragment, 0: Middle continuation
                $this->firstFragment = false;
            }
        }

        // Prepare header using binary pack
        if ($L <= 125) {
            $header = pack('CC', $firstByte, $L | 128);
        } elseif ($L <= 65535) {
            $header = pack('CCn', $firstByte, 126 | 128, $L);
        } else {
            $header = pack('CCJ', $firstByte, 127 | 128, $L);
        }

        // Generate 4-byte mask and apply fast native C-level string bitwise XOR
        $masks = random_bytes(4);
        $maskedPayload = $M ^ str_pad('', $L, $masks);

        return $header . $masks . $maskedPayload;
    }

    final function decodeFromServer($frame) {
        if (!$frame) {
            $this->opcode = 8; // force close connection
            $this->fin = true;
            $this->length = 0;
            $this->frame = '';
            return '';
        }

        // Detects and processes WebSocket frames, including ping, pong, and fragmented frames.
        $b1 = ord($frame[0]);
        $this->fin = ($b1 & 0x80) !== 0; // FIN bit
        $this->opcode = $b1 & 0x0F;      // Opcode
        $this->frame = $frame;

        $length = ord($frame[1]) & 0x7F; // Mask length byte to get payload length
        $poff = 2;                       // Default payload offset for lengths <= 125

        if ($length === 126) {
            $length = unpack('n', substr($frame, 2, 2))[1];
            $poff = 4;
        } elseif ($length === 127) {
            $length = unpack('J', substr($frame, 2, 8))[1];
            $poff = 10;
        }

        $this->length = $length;
        return substr($frame, $poff, $length); // Extract payload data starting at offset
    }
}