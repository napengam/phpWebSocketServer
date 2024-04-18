<?php

trait RFC_6455 {

    public function Encode($M) {
        $L = strlen($M);
        $bHead = [];

        // Determine the opcode and set the first byte of the header
        $bHead[0] = $this->opcode === 10 ? 138 : ($this->opcode === 9 ? 137 : 129);

        // Set the opcode to 1 for continuation frames
        $this->opcode = 1;

        if ($L <= 125) {
            $bHead[1] = $L;
        } elseif ($L <= 65535) {
            $bHead[1] = 126;
            $bHead[2] = ($L >> 8) & 255;
            $bHead[3] = $L & 255;
        } else {
            $bHead[1] = 127;
            for ($i = 0; $i < 8; $i++) {
                $bHead[$i + 2] = ($L >> (56 - $i * 8)) & 255;
            }
        }

        // Convert the header bytes to characters
        $header = implode(array_map("chr", $bHead));

        // Concatenate the header with the message
        return $header . $M;
    }

    public function readDecode($socketID) {
        // Detect ping or pong frame, or fragments

        $socket = $this->Sockets[$socketID];
        $frame = fread($socket, 8192);

        if (empty($frame)) {
            $this->opcode = 8;
            return;
        }

        $this->fin = ord($frame[0]) & 128;
        $this->opcode = ord($frame[0]) & 15;
        $length = ord($frame[1]) & 127;

        if ($length === 0) {
            $this->opcode = 8;
            return;
        }

        $moff = $poff = 0;
        if ($length <= 125) {
            $moff = 2;
            $poff = 6;
        } else if ($length === 126) {
            $length = unpack('n', substr($frame, 2, 2))[1];
            $moff = 4;
            $poff = 8;
        } else if ($length === 127) {
            $length = unpack('J', substr($frame, 2, 8))[1];
            $moff = 10;
            $poff = 14;
        }

        $masks = substr($frame, $moff, 4);
        $data = substr($frame, $poff, $length);

        $plength = $length - strlen($data);
        while ($plength > 0) {
            $chunk = fread($socket, min(8192, $plength));
            $data .= $chunk;
            $plength -= strlen($chunk);
        }

        $text = '';
        $maskBytes = array_map('ord', str_split($masks));
        $maskBytesCount = count($maskBytes);
        $j = 0;

        for ($i = 0; $i < $length; $i++) {
            $text .= chr(ord($data[$i]) ^ $maskBytes[$j]);
            $j = ($j + 1) % $maskBytesCount;
        }

        return $text;
    }

    protected function Handshake($Socket, $Buffer) {

        $errorResponds = [];
        $SocketID = intval($Socket);
        $Headers = [];
        $reqResource = [];
        $Lines = explode("\n", $Buffer);

        foreach ($Lines as $Line) {
            if (strpos($Line, ":") !== false) {
                $Header = explode(":", $Line, 2);
                $Headers[strtolower(trim($Header[0]))] = trim($Header[1]);
            } else if (stripos($Line, "get ") !== false) {
                preg_match("/GET (.*) HTTP/i", $Buffer, $reqResource);
                $Headers['get'] = trim($reqResource[1]);
            }
        }
        $this->Log("Handshake: " . $Headers['get'] . "Client");

        foreach (['host', 'origin', 'sec-websocket-key', 'upgrade', 'connection', 'sec-websocket-version'] as $key) {
            if (isset($Headers[$key]) === false) {
                fwrite($Socket, "HTTP/1.1 400 Bad Request", strlen("HTTP/1.1 400 Bad Request"));
                $this->onError($SocketID, "Handshake aborted - HTTP/1.1 400 Bad Request");
                $this->Close($Socket);
                return false;
            }
        }

        if (strtolower($Headers['upgrade']) != 'websocket' ||
                strpos(strtolower($Headers['connection']), 'upgrade') === FALSE) {
            $errorResponds[] = "HTTP/1.1 400 Bad Request";
        }
        if ($Headers['sec-websocket-version'] != 13) {
            $errorResponds[] = "HTTP/1.1 426 Upgrade Required\r\nSec-WebSocketVersion: 13";
        }
        if (!isset($Headers['get'])) {
            $errorResponds[] = "HTTP/1.1 405 Method Not Allowed\r\n\r\n";
        }
        if (count($errorResponds) > 0) {
            $message = implode("\r\n", $errorResponds);
            fwrite($Socket, $message, strlen($message));
            $this->onError($SocketID, "Handshake aborted - [" . trim($message) . "]");
            $this->Close($Socket);
            return false;
        }
        $Token = "";
        $sah1 = sha1($Headers['sec-websocket-key'] . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11");
        $Token = base64_encode(pack('H*', $sah1));
        $statusLine = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $Token\r\n\r\n";
        fwrite($Socket, $statusLine, strlen($statusLine));

        $clType = 'websocket';
        if (isset($Headers['client-type'])) {
            strcasecmp($Headers['client-type'], 'php') == 0 ? $clType = 'php' : '';
        }
        $this->Clients[$SocketID]->clientType = $clType;

        if (isset($Headers['ident'])) {
            $this->Clients[$SocketID]->ident = $Headers['ident'];
        }

        $this->Log('ClientType:' . $this->Clients[$SocketID]->clientType);

        $this->Clients[$SocketID]->Handshake = true;
        if (isset($this->allApps[$Headers['get']])) {
            $this->Clients[$SocketID]->app = $this->allApps[$Headers['get']];
        }
        return true;
    }

    function extractIPort($inIP) {

        // [2001:db8:85a3:8d3:1319:8a2e:370:7348]:8765   ?????     
        //  2001:db8:85a3:8d3:1319:8a2e:370:7348
        // 127.0.0.1:1234

        $inIP = preg_replace('/ {1,}/', '', $inIP);
        $c = mb_substr($inIP, 0, 1);

        if ($c == '[') { // ipv6
            $p = mb_strpos($inIP, ']');
            if ($p > 0) { //[ipv6]:port
                $ip = mb_substr($inIP, 1, $p - 1);
                $port = trim(mb_substr($inIP, $p + 2));
                return (object) ['ip' => $ip, 'port' => $port];
            } else {
                return (object) ['ip' => '', 'port' => ''];
            }
        }
        if (mb_strpos($inIP, '.')) { // ipv4
            $p = mb_strpos($inIP, ':');
            if ($p > 0) { // ipv4:port
                $ip = mb_substr($inIP, 0, $p);
                $port = trim(mb_substr($inIP, $p + 1));
                return (object) ['ip' => $ip, 'port' => $port];
            }
        }
        return (object) ['ip' => $inIP, 'port' => ''];
    }
}
