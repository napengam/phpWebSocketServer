<?php

trait RFC_6455 {

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
        $this->opcode = 1;
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

    public function Decode($frame) {
        // detect ping or pong frame, or fragments

        $this->fin = ord($frame[0]) & 128;
        $this->opcode = ord($frame[0]) & 15;
        $length = ord($frame[1]) & 127;
        if ($length == 0) {
            $this->opcode = 8;
            return '';
        }
        if ($length <= 125) {
            $moff = 2;
            $poff = 6;
        } else if ($length == 126) {
            $l0 = ord($frame[2]) << 8;
            $l1 = ord($frame[3]);
            $length = ($l0 | $l1);
            $moff = 4;
            $poff = 8;
        } else if ($length == 127) {
            $l0 = ord($frame[2]) << 56;
            $l1 = ord($frame[3]) << 48;
            $l2 = ord($frame[4]) << 40;
            $l3 = ord($frame[5]) << 32;
            $l4 = ord($frame[6]) << 24;
            $l5 = ord($frame[7]) << 16;
            $l6 = ord($frame[8]) << 8;
            $l7 = ord($frame[9]);
            $length = ( $l0 | $l1 | $l2 | $l3 | $l4 | $l5 | $l6 | $l7);
            $moff = 10;
            $poff = 14;
        }

        $masks = substr($frame, $moff, 4);
        $data = substr($frame, $poff, $length); // hgs 30.09.2016
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

    public function readDecode($socketID) {
        // detect ping or pong frame, or fragments

        $socket = $this->Sockets[$socketID];
        $frame = fread($socket, 8192);
        if (strlen($frame) == 0) {
            $this->opcode = 8;
            return;
        }

        $this->fin = ord($frame[0]) & 128;
        $this->opcode = ord($frame[0]) & 15;
        $length = ord($frame[1]) & 127;

        if ($length == 0) {
            $this->opcode = 8;
            return;
        }
        if ($length <= 125) {
            $moff = 2;
            $poff = 6;
        } else if ($length == 126) {
            $l0 = ord($frame[2]) << 8;
            $l1 = ord($frame[3]);
            $length = ($l0 | $l1);
            $moff = 4;
            $poff = 8;
        } else if ($length == 127) {
            $l0 = ord($frame[2]) << 56;
            $l1 = ord($frame[3]) << 48;
            $l2 = ord($frame[4]) << 40;
            $l3 = ord($frame[5]) << 32;
            $l4 = ord($frame[6]) << 24;
            $l5 = ord($frame[7]) << 16;
            $l6 = ord($frame[8]) << 8;
            $l7 = ord($frame[9]);
            $length = ( $l0 | $l1 | $l2 | $l3 | $l4 | $l5 | $l6 | $l7);
            $moff = 10;
            $poff = 14;
        }

        $masks = substr($frame, $moff, 4);
        $data = substr($frame, $poff, $length); // hgs 30.09.2016

        $plength = $length;
        $plength -= strlen($data);
        while ($plength > 0) {
            $chunk = fread($socket, 8192);
            $data .= $chunk;
            $plength -= strlen($chunk);
        }

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
        for ($i = 0; $i < 20; $i++) {
            $Token .= chr(hexdec(substr($sah1, $i * 2, 2)));
        }
        $Token = base64_encode($Token);
        $statusLine = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $Token\r\n\r\n";
        fwrite($Socket, $statusLine, strlen($statusLine));

        if (isset($Headers['client-type'])) {
            if (strcasecmp($Headers['client-type'], 'php') == 0) {
                $this->Clients[$SocketID]->clientType = 'tcp';
            } else {
                $this->Clients[$SocketID]->clientType = 'websocket';
            }
        } else {
            $this->Clients[$SocketID]->clientType = 'websocket';
        }
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

        $inIP = trim($inIP);
        $ip = $port = '';
        $n = mb_strlen($inIP);
        for ($i = 0; $i < $n; $i++) {
            $c = mb_substr($inIP, $i, 1);
            if ($c == '[' && $i == 0) {
                $p = mb_strpos($inIP, ']');
                if ($p > 0) {
                    $ip = mb_substr($inIP, 1, $p - 1);
                    if ($p + 1 < $n) {
                        $c = mb_substr($inIP, $p + 1, 1);
                        if ($c == ':') {
                            $port = mb_substr($inIP, $p + 2);
                        }
                    }
                    break;
                }
            } else if ($c == ':') {
                $ip = mb_substr($inIP, 0, $n);
            } else if ($c == '.') {
                $p = mb_strpos($inIP, ':');
                if ($p > 0) {
                    $ip = mb_substr($inIP, 0, $p);
                    $port = mb_substr($inIP, $p + 1);
                    break;
                } else {
                    $ip = mb_substr($inIP, 0, $n);
                    break;
                }
            }
        }
        return (object) ['ip' => $ip, 'port' => $port];
    }

}
