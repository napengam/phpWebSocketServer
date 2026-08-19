<?php

trait RFC_6455 {

    private $buffers = [];
    private $fragmentBuffer = [];

    public function encode($message) {
        $length = strlen($message);
        $header = [];

        // Set the first byte based on the opcode
        $header[] = ($this->opcode === 10) ? 138 : (($this->opcode === 9) ? 137 : 129);

        // Reset opcode to 1 for continuation frames
        $this->opcode = 1;

        // Determine the payload length and construct the header accordingly
        if ($length <= 125) {
            $header[] = $length;
        } elseif ($length <= 65535) {
            $header[] = 126;
            $header[] = ($length >> 8) & 0xFF;
            $header[] = $length & 0xFF;
        } else {
            $header[] = 127;
            // Native 64-bit pack safely avoids bitshift overflows
            return implode(array_map("chr", $header)) . pack('J', $length) . $message;
        }

        // Create the final header as a string and return it concatenated with the message
        return implode(array_map("chr", $header)) . $message;
    }

    public function readDecode($socketID) {
        if (!isset($this->Sockets[$socketID])) {
            return [];
        }

        $socket = $this->Sockets[$socketID];

        if (!isset($this->buffers[$socketID])) {
            $this->buffers[$socketID] = '';
        }

        if (!isset($this->fragmentBuffer[$socketID])) {
            $this->fragmentBuffer[$socketID] = '';
        }

        $messages = [];
        $chunk = fread($socket, 8192);

        if ($chunk === false || $chunk === '') {
            unset($this->buffers[$socketID], $this->fragmentBuffer[$socketID]);
            return [['opcode' => 8, 'data' => '']];
        }

        $this->buffers[$socketID] .= $chunk;
        $buffer = &$this->buffers[$socketID];

        // DoS Protection: Prevent unbounded memory usage per client
        if (strlen($buffer) > $this->maxBufferSize) {
            unset($this->buffers[$socketID], $this->fragmentBuffer[$socketID]);
            return [['opcode' => 8, 'data' => 'Buffer limit exceeded']];
        }

        while (true) {
            if (strlen($buffer) < 2) {
                break;
            }

            $b1 = ord($buffer[0]);
            $b2 = ord($buffer[1]);

            $fin = ($b1 & 128) !== 0;
            $opcode = $b1 & 15;

            $masked = ($b2 & 128) !== 0;
            $length = $b2 & 127;

            $offset = 2;

            // Extended length
            if ($length === 126) {
                if (strlen($buffer) < 4) {
                    break;
                }
                $length = unpack('n', substr($buffer, 2, 2))[1];
                $offset = 4;
            } elseif ($length === 127) {
                if (strlen($buffer) < 10) {
                    break;
                }
                $length = unpack('J', substr($buffer, 2, 8))[1];
                $offset = 10;
            }

            // Masking check
            if ($masked) {
                if (strlen($buffer) < $offset + 4) {
                    break;
                }
                $mask = substr($buffer, $offset, 4);
                $offset += 4;
            } else {
                $mask = '';
            }

            // Payload completeness check
            if (strlen($buffer) < $offset + $length) {
                break;
            }

            $payload = substr($buffer, $offset, $length);

            // Unmask payload
            if ($masked) {
                $data = '';
                for ($i = 0; $i < $length; $i++) {
                    $data .= $payload[$i] ^ $mask[$i % 4];
                }
            } else {
                $data = $payload;
            }

            // Shorten internal buffer
            $buffer = substr($buffer, $offset + $length);

            // --- RFC 6455 Fragment Handling ---
            if ($opcode === 0) {
                // Continuation frame
                $this->fragmentBuffer[$socketID] .= $data;

                if (strlen($this->fragmentBuffer[$socketID]) > $this->maxBufferSize) {
                    unset($this->buffers[$socketID], $this->fragmentBuffer[$socketID]);
                    return [['opcode' => 8, 'data' => 'Fragment buffer limit exceeded']];
                }

                if ($fin) {
                    $messages[] = [
                        'opcode' => 1,
                        'data' => $this->fragmentBuffer[$socketID]
                    ];
                    $this->fragmentBuffer[$socketID] = '';
                }

                continue;
            }

            if (!$fin) {
                // Initial fragment frame
                $this->fragmentBuffer[$socketID] = $data;
                continue;
            }

            // --- Normal Standalone Message ---
            $messages[] = [
                'opcode' => $opcode,
                'data' => $data
            ];
        }

        return $messages;
    }

    protected function Handshake($Socket, $Buffer) {

        $SocketID = intval($Socket);
        $Client = $this->Clients[$SocketID];
        $Headers = [];
        $errorResponses = [];
        $lines = explode("\n", $Buffer);

        // Parse headers and extract requested resource
        foreach ($lines as $line) {
            if (strpos($line, ":") !== false) {
                [$key, $value] = explode(":", $line, 2);
                $Headers[strtolower(trim($key))] = trim($value);
            } elseif (stripos($line, "get ") === 0) {
                if (preg_match("/GET (.*) HTTP/i", $line, $reqResource)) {
                    $Headers['get'] = trim($reqResource[1]);
                }
            }
        }
        // Save parsed headers onto the Client object
        $Client->headers = $Headers;
        $this->Log("Handshake: " . ($Headers['get'] ?? 'Unknown') . " Client");

        // Check required headers
        $requiredHeaders = ['host', 'origin', 'sec-websocket-key', 'upgrade', 'connection', 'sec-websocket-version'];
        foreach ($requiredHeaders as $key) {
            if (!isset($Headers[$key])) {
                $this->sendErrorResponse($Socket, $SocketID, "HTTP/1.1 400 Bad Request", "Missing header: $key");
                return false;
            }
        }

        // Validate WebSocket upgrade headers
        if (strtolower($Headers['upgrade']) !== 'websocket' || stripos($Headers['connection'], 'upgrade') === false) {
            $errorResponses[] = "HTTP/1.1 400 Bad Request";
        }

        // Validate WebSocket version
        if ($Headers['sec-websocket-version'] !== '13') {
            $errorResponses[] = "HTTP/1.1 426 Upgrade Required\r\nSec-WebSocket-Version: 13";
        }

        // Validate HTTP method
        if (empty($Headers['get'])) {
            $errorResponses[] = "HTTP/1.1 405 Method Not Allowed\r\n\r\n";
        }

        // Send accumulated error responses if any
        if (!empty($errorResponses)) {
            $this->sendErrorResponse($Socket, $SocketID, implode("\r\n", $errorResponses), "Invalid handshake request");
            return false;
        }

        // Complete the WebSocket handshake
        $acceptToken = base64_encode(pack('H*', sha1($Headers['sec-websocket-key'] . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11")));
        $statusLine = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $acceptToken\r\n\r\n";
        fwrite($Socket, $statusLine);

        // Set client type and metadata if available
        $clientType = (strcasecmp($Headers['client-type'] ?? '', 'php') === 0) ? 'php' : 'websocket';
        $client = $this->Clients[$SocketID];
        $client->clientType = $clientType;
        $client->ident = $Headers['ident'] ?? null;
        $client->allowRemote = $Headers['allowRemote'] ?? null;
        $client->Handshake = true;

        // Log and associate the app if configured
        $this->Log('ClientType: ' . $clientType);
        if (isset($this->allApps[$Headers['get']])) {
            $client->app = $this->allApps[$Headers['get']];
        }
        if ($clientType === 'websocket') {
            if (SessionAuthHandler::authenticateClient($Buffer) === false) {
                $this->onError($SocketID, "Client handshake status: false");
                return false;
            }
        }
        return true;
    }

    private function sendErrorResponse($Socket, $SocketID, $message, $logMessage) {
        fwrite($Socket, $message);
        $this->onError($SocketID, "Handshake aborted - $logMessage");
        $this->Close($Socket);
    }

    function extractIPort($inIP) {
        $inIP = preg_replace('/\s+/', '', $inIP);

        if (preg_match('/^\[([^\]]+)\](?::(\d+))?$/', $inIP, $matches) || preg_match('/^([0-9.]+)(?::(\d+))?$/', $inIP, $matches)) {
            return (object) ['ip' => $matches[1], 'port' => $matches[2] ?? ''];
        }

        return (object) ['ip' => $inIP, 'port' => ''];
    }

    public function sendPong($socketID, $payload = '') {
        if (!isset($this->Sockets[$socketID])) {
            return;
        }

        $socket = $this->Sockets[$socketID];
        $frame = '';
        $length = strlen($payload);

        // FIN + Pong (0xA)
        $frame .= chr(0x80 | 0x0A);

        // Length calculation
        if ($length <= 125) {
            $frame .= chr($length);
        } elseif ($length <= 65535) {
            $frame .= chr(126);
            $frame .= pack('n', $length);
        } else {
            $frame .= chr(127);
            $frame .= pack('J', $length);
        }

        if ($length > 0) {
            $frame .= $payload;
        }

        fwrite($socket, $frame);
    }
}
