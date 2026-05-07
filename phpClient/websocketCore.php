<?php

class websocketCore {

    public $prot, $connected = false, $firstFragment = true, $finBit = true,
            $ident, $socketMaster, $key, $expectedToken, $errorHandshake, $fin, $opcode,
            $frame, $length, $fromUUID, $timeout = 2;
    private $buffer, $fragmentBuffer, $fragmentOpcode;

    function __construct($Address, $ident = '') {
        $this->ident = $ident;
        $context = stream_context_create();

        // Extract protocol and set default port
        $parts = explode('://', $Address, 2);
        $protocol = (count($parts) > 1) ? strtolower($parts[0]) : 'tcp';
        $Address = (count($parts) > 1) ? $parts[1] : $Address;

        $isSecure = ($protocol === 'ssl' || $protocol === 'wss');
        $defaultPort = $isSecure ? '443' : '80';
        $prot = $isSecure ? 'ssl://' : 'tcp://';

        if ($isSecure) {
            stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
            stream_context_set_option($context, 'ssl', 'verify_peer_name', false);
        }

        // Extract endpoint and default to '/'
        [$host, $app] = explode('/', $Address, 2) + [null, '/'];
        $app = '/' . $app;

        // Extract port if specified
        [$host, $port] = explode(':', $host, 2) + [null, $defaultPort];
        $addressWithPort = "$prot$host:$port";

        $errno = 0;
        $errstr = '';
        $this->socketMaster = stream_socket_client($addressWithPort, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);

        if (!$this->socketMaster) {
            $this->connected = false;
            return false;
        }

        $this->connected = true;
        fwrite($this->socketMaster, $this->setHandshake($host, $app));
        $buffer = fread($this->socketMaster, 1024);

        if (!$this->getHandshake($buffer)) {
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

        if ($this->connected === false) {
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
                    $this->writeSocket($m, strlen($m));
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
                $continue = false;
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
        } while ($this->fin == false);
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
        $this->key = random_bytes(16);
        $key = base64_encode($this->key);

        // Expected token calculated from key and the WebSocket GUID
        $sah1 = sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11");
        $this->expectedToken = base64_encode(hex2bin($sah1));

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
        $Headers = [];
        $this->errorHandshake = $Buffer;
        $Lines = explode("\n", $Buffer);
        foreach ($Lines as $Line) {
            if (strpos($Line, ":") !== false) {
                $Header = explode(":", $Line, 2);
                $Headers[strtolower(trim($Header[0]))] = trim($Header[1]);
            } else if (stripos($Line, "HTTP/") !== false) {
                $Headers['101'] = trim($Line);
            }
        }
        foreach (['101', 'upgrade', 'connection', 'sec-websocket-accept']as $key) {
            if (isset($Headers[$key]) === false) {
                return false;
            }
        }

        if (stripos($Headers['101'], "HTTP/1.1 101") === false) {
            return false;
        }
        if (strcasecmp($Headers['upgrade'], 'websocket') <> 0) {
            return false;
        }
        if (strcasecmp($Headers['connection'], 'Upgrade') <> 0) {
            return false;
        }
        if ($Headers['sec-websocket-accept'] != $this->expectedToken) {
            return false;
        }
        $this->errorHandshake = '';
        return true;
    }

    final function encodeForServer($M) {
        $L = strlen($M);
        $bHead = [];

        // Set the first byte based on the opcode and fragment
        if ($L === 0) {
            $bHead[] = 136; // Close frame if message length = 0
        } else {
            $bHead[] = $this->finBit ? ($this->firstFragment ? ($this->opcode === 10 ? 138 : 129) : 128) : ($this->firstFragment ? 1 : 0);

            $this->firstFragment = !$this->finBit;
        }

        // Prepare the payload length and mask bit
        if ($L <= 125) {
            $bHead[] = $L | 128;
        } elseif ($L <= 65535) {
            $bHead = array_merge($bHead, [126 | 128, ($L >> 8) & 255, $L & 255]);
        } else {
            $bHead = array_merge($bHead, [127 | 128, ($L >> 56) & 255, ($L >> 48) & 255, ($L >> 40) & 255, ($L >> 32) & 255, ($L >> 24) & 255, ($L >> 16) & 255, ($L >> 8) & 255, $L & 255]);
        }

        // Generate masking key and apply it to the message payload
        $masks = random_bytes(4);
        $maskedPayload = '';

        for ($i = 0; $i < $L; $i++) {
            $maskedPayload .= $M[$i] ^ $masks[$i % 4];
        }

        // Combine header, masking key, and masked payload
        return implode(array_map("chr", $bHead)) . $masks . $maskedPayload;
    }

    final function decodeFromServer($frame) {
        if ($frame === false || $frame == '' || $frame == null || !is_string($frame)) {
            $this->opcode = 8; // force close connetion
            $this->fin = true;
            $this->length = 0;
            $this->frame = '';
            return '';
        }

        // Detects and processes WebSocket frames, including ping, pong, and fragmented frames.
        $this->fin = (ord($frame[0]) & 0b10000000) !== 0; // FIN bit
        $this->opcode = ord($frame[0]) & 0b00001111;       // Opcode
        $this->frame = $frame;

        $length = ord($frame[1]) & 0b01111111; // Mask length byte to get payload length
        $poff = 2; // Default payload offset for lengths <= 125

        if ($length === 126) {
            $length = (ord($frame[2]) << 8) | ord($frame[3]);
            $poff = 4;
        } elseif ($length === 127) {
            // Assemble 64-bit length for extended payloads
            $length = 0;
            for ($i = 2; $i < 10; $i++) {
                $length = ($length << 8) | ord($frame[$i]);
            }
            $poff = 10;
        }

        $this->length = $length;
        return substr($frame, $poff, $length); // Extract payload data starting at offset
    }

    final function decodeFromServerAll($initial = '') {
        $socket = $this->socketMaster;

        if ($this->connected === false || !$socket) {
            $this->opcode = 8;
            return [];
        }

        if (!isset($this->buffer)) {
            $this->buffer = '';
        }

        if (!isset($this->fragmentBuffer)) {
            $this->fragmentBuffer = '';
        }

        if (!isset($this->fragmentOpcode)) {
            $this->fragmentOpcode = null;
        }

        if ($initial !== '') {
            $this->buffer .= $initial;
        }

        // --- Daten nachladen (non-blocking freundlich) ---
        $chunk = fread($socket, 8192);

        if ($chunk !== false && $chunk !== '') {
            $this->buffer .= $chunk;
        }

        $messages = [];

        // --- Loop: mehrere Frames verarbeiten ---
        while (true) {

            if (strlen($this->buffer) < 2) {
                break;
            }

            $b1 = ord($this->buffer[0]);
            $b2 = ord($this->buffer[1]);

            $fin = (($b1 & 0x80) !== 0);
            $opcode = ($b1 & 0x0F);
            $masked = (($b2 & 0x80) !== 0);
            $length = ($b2 & 0x7F);

            $offset = 2;

            // --- Extended Length ---
            if ($length === 126) {
                if (strlen($this->buffer) < 4) {
                    break;
                }

                $length = ((ord($this->buffer[2]) << 8) | ord($this->buffer[3]));
                $offset = 4;
            } else {
                if ($length === 127) {
                    if (strlen($this->buffer) < 10) {
                        break;
                    }

                    $length = 0;

                    for ($i = 2; $i < 10; $i++) {
                        $length = (($length << 8) | ord($this->buffer[$i]));
                    }

                    $offset = 10;
                }
            }

            // --- Mask ---
            $maskKey = '';

            if ($masked) {
                if (strlen($this->buffer) < ($offset + 4)) {
                    break;
                }

                $maskKey = substr($this->buffer, $offset, 4);
                $offset += 4;
            }

            // --- Payload komplett? ---
            if (strlen($this->buffer) < ($offset + $length)) {
                break;
            }

            $payload = substr($this->buffer, $offset, $length);

            // --- Buffer kürzen ---
            $this->buffer = substr($this->buffer, $offset + $length);

            // --- Unmask ---
            if ($masked) {
                $decoded = '';

                for ($i = 0; $i < $length; $i++) {
                    $decoded .= ($payload[$i] ^ $maskKey[$i % 4]);
                }

                $payload = $decoded;
            }

            // --- Control Frames ---
            if ($opcode === 0x8) {
                $this->connected = false;
                break;
            } else {
                if ($opcode === 0x9) {
                    $this->sendPong($payload);
                    continue;
                } else {
                    if ($opcode === 0xA) {
                        continue;
                    }
                }
            }

            // --- Fragmentierung ---
            if ($opcode === 0x0) {
                // continuation
                $this->fragmentBuffer .= $payload;

                if ($fin) {
                    $messages[] = $this->fragmentBuffer;
                    $this->fragmentBuffer = '';
                    $this->fragmentOpcode = null;
                }
            } else {
                // neuer Frame
                if ($fin) {
                    // komplett
                    $messages[] = $payload;
                } else {
                    // Start Fragment
                    $this->fragmentOpcode = $opcode;
                    $this->fragmentBuffer = $payload;
                }
            }
        }

        return $messages;
    }

    final function readSocketAll() {
        if ($this->connected === false) {
            return '';
        }

        $messageParts = [];

        do {
            // Read one complete WebSocket frame (decodeFromServer handles partial TCP reads)
            $payload = $this->decodeFromServer();

            // Handle socket errors or closed connection
            if ($payload === '' && $this->opcode === 8) {
                $this->silent(); // gracefully close
                return '';
            }

            // Handle special opcodes
            if ($this->opcode === 9) { // Ping
                $this->writeSocket($payload, strlen($payload), 0xA); // send Pong
                continue; // wait for next frame
            }

            if ($this->opcode === 10) { // Pong
                // ignore, just continue reading
                continue;
            }

            if ($this->opcode === 8) { // Close
                $this->silent();
                return '';
            }

            // Normal data frame: accumulate payload
            $messageParts[] = $payload;

            // Continue reading if fragmented (FIN == false)
        } while ($this->fin === false);

        // Join all message fragments into a complete message
        return implode('', $messageParts);
    }
}
