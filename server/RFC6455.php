<?php

trait RFC6455 {

    public function Encode($M) {
        $L = strlen($M);
        $bHead = [];
        if ($this->opcode == 10) { // POng
            $bHead[0] = 138; // send pong
        } else if ($this->opcode == 9) { // PIng
            $bHead[0] = 137; // send ping
        } else {
            $bHead[0] = 129; // 0x1 text frame (FIN + opcode)
        }
        if ($L <= 125) {
            $bHead[1] = $L;
        } else if ($L >= 126 && $L <= 65535) {
            $bHead[1] = 126;
            $bHead[2] = ( $L >> 8 ) & 255;
            $bHead[3] = ( $L ) & 255;
        } else {
            $bHead[1] = 127;
            $bHead[2] = ( $L >> 56 ) & 255;
            $bHead[3] = ( $L >> 48 ) & 255;
            $bHead[4] = ( $L >> 40 ) & 255;
            $bHead[5] = ( $L >> 32 ) & 255;
            $bHead[6] = ( $L >> 24 ) & 255;
            $bHead[7] = ( $L >> 16 ) & 255;
            $bHead[8] = ( $L >> 8 ) & 255;
            $bHead[9] = ( $L ) & 255;
        }
        return (implode(array_map("chr", $bHead)) . $M);
    }

    public function Decode($payload) {
        // detect ping or pong frame, or fragments

        $this->fin = ord($payload[0]) & 128;
        $this->opcode = ord($payload[0]) & 15;
        $length = ord($payload[1]) & 127;

        if ($length <= 125) {
            $moff = 2;
            $poff = 6;
        } else if ($length == 126) {
            $l0 = ord($payload[2]) << 8;
            $l1 = ord($payload[3]);
            $length = ($l0 | $l1);
            $moff = 4;
            $poff = 8;
        } else if ($length == 127) {
            $l0 = ord($payload[2]) << 56;
            $l1 = ord($payload[3]) << 48;
            $l2 = ord($payload[4]) << 40;
            $l3 = ord($payload[5]) << 32;
            $l4 = ord($payload[6]) << 24;
            $l5 = ord($payload[7]) << 16;
            $l6 = ord($payload[8]) << 8;
            $l7 = ord($payload[9]);
            $length = ( $l0 | $l1 | $l2 | $l3 | $l4 | $l5 | $l6 | $l7);
            $moff = 10;
            $poff = 14;
        }

        $masks = substr($payload, $moff, 4);
        $data = substr($payload, $poff, $length); // hgs 30.09.2016
        $text = '';
        $m0 = $masks[0];
        $m1 = $masks[1];
        $m2 = $masks[2];
        $m3 = $masks[3];
        for ($i = 0; $i < $length;) {
            $text .= $data[$i++] ^ $m0;
            if ($i < $length) {
                $text .= $data[$i++] ^ $m1;
                if ($i < $length) {
                    $text .= $data[$i++] ^ $m2;
                    if ($i < $length) {
                        $text .= $data[$i++] ^ $m3;
                    }
                }
            }
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
        if (!isset($Headers['host']) || !isset($Headers['origin']) ||
                !isset($Headers['sec-websocket-key']) ||
                (!isset($Headers['upgrade']) || strtolower($Headers['upgrade']) != 'websocket') ||
                (!isset($Headers['connection']) || strpos(strtolower($Headers['connection']), 'upgrade') === FALSE)) {
            $errorResponds[] = "HTTP/1.1 400 Bad Request";
        }
        if (!isset($Headers['sec-websocket-version']) || strtolower($Headers['sec-websocket-version']) != 13) {
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
        for ($i = 0; $i < 20; $i++) {
            $Token .= chr(hexdec(substr($sah1, $i * 2, 2)));
        }
        $Token = base64_encode($Token);
        $statusLine = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $Token\r\n\r\n";
        fwrite($Socket, $statusLine, strlen($statusLine));
        if (stripos($Headers['get'], 'web') !== false) {
            $this->Clients[$SocketID]->clientType = 'websocket';
        } else {
            $this->Clients[$SocketID]->clientType = 'tcp';
        }
        $this->Log('ClientType:' . $this->Clients[$SocketID]->clientType);
        $this->Clients[$SocketID]->Handshake = true;
        if (isset($this->allApps[$Headers['get']])) {
            $this->Clients[$SocketID]->app = $this->allApps[$Headers['get']];
        }
        return true;
    }

    public function extractIP($inIP) {

        // [2001:db8:85a3:8d3:1319:8a2e:370:7348]:8765   ?????     
        //  2001:db8:85a3:8d3:1319:8a2e:370:7348 
        // 127.0.0.1:1234

        $inIP = trim($inIP);

        $n = mb_strlen($inIP);
        for ($i = 0; $i < $n; $i++) {
            $c = mb_substr($inIP, $i, 1);
            if ($c == '[' && $i == 0) {
                $p = mb_strpos($inIP, ']');
                if ($p > 0) {
                    return mb_substr($inIP, 1, $p - 1);
                }
            } else if ($c == ':') {
                return mb_substr($inIP, 0, $n);
            } else if ($c == '.') {
                $p = mb_strpos($inIP, ':');
                if ($p > 0) {
                    return mb_substr($inIP, 0, $p);
                } else {
                    return mb_substr($inIP, 0, $n);
                }
            }
        }
    }

}
