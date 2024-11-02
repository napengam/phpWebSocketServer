<?php

trait RFC_6455 {

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
            for ($i = 7; $i >= 0; $i--) {
                $header[] = ($length >> ($i * 8)) & 0xFF;
            }
        }

        // Create the final header as a string and return it concatenated with the message
        return implode(array_map("chr", $header)) . $message;
    }

    public function readDecode($socketID) {
        $socket = $this->Sockets[$socketID];
        $frame = fread($socket, 8192);

        if (empty($frame)) {
            $this->opcode = 8; // Close frame if empty
            return;
        }

        $this->fin = (ord($frame[0]) & 128) !== 0;
        $this->opcode = ord($frame[0]) & 15;
        $length = ord($frame[1]) & 127;

        if ($length === 0) {
            $this->opcode = 8;
            return;
        }

        $masks = '';
        $dataOffset = 2;

        if ($length === 126) {
            $length = unpack('n', substr($frame, 2, 2))[1];
            $dataOffset = 4;
        } elseif ($length === 127) {
            $length = unpack('J', substr($frame, 2, 8))[1];
            $dataOffset = 10;
        }

        $masks = substr($frame, $dataOffset, 4);
        $data = substr($frame, $dataOffset + 4, $length);

        // Read additional chunks if necessary to complete the payload
        $remaining = $length - strlen($data);
        while ($remaining > 0) {
            $chunk = fread($socket, min(8192, $remaining));
            $data .= $chunk;
            $remaining -= strlen($chunk);
        }

        // Apply masking
        $text = '';
        for ($i = 0; $i < $length; $i++) {
            $text .= $data[$i] ^ $masks[$i % 4];
        }

        return $text;
    }

    protected function Handshake($Socket, $Buffer) {
        $SocketID = (int) $Socket;
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
        if (strtolower($Headers['upgrade']) !== 'websocket' ||
                stripos($Headers['connection'], 'upgrade') === false) {
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

        return true;
    }

    // Helper method for sending error responses
    private function sendErrorResponse($Socket, $SocketID, $message, $logMessage) {
        fwrite($Socket, $message);
        $this->onError($SocketID, "Handshake aborted - $logMessage");
        $this->Close($Socket);
    }

    function extractIPort($inIP) {
        // Trim spaces and match IPv4 or IPv6 addresses with optional port
        // [2001:db8:85a3:8d3:1319:8a2e:370:7348]:8765   ?????     
        //  2001:db8:85a3:8d3:1319:8a2e:370:7348
        // 127.0.0.1:1234


        $inIP = preg_replace('/\s+/', '', $inIP);

        // Match patterns for IPv6 with port, IPv6 without port, IPv4 with port, and IPv4 without port
        if (preg_match('/^\[([^\]]+)\](?::(\d+))?$/', $inIP, $matches) || // IPv6 with optional port
                preg_match('/^([0-9.]+)(?::(\d+))?$/', $inIP, $matches)) {      // IPv4 with optional port
            return (object) ['ip' => $matches[1], 'port' => $matches[2] ?? ''];
        }

        // Return as-is if no pattern matches (fallback for invalid input)
        return (object) ['ip' => $inIP, 'port' => ''];
    }
}
