<?php

trait coreFunc {

    public function Encode($M) {
        // inspiration for Encode() method : 
        // http://stackoverflow.com/questions/8125507/how-can-i-send-and-receive-websocket-messages-on-the-server-side
        $L = strlen($M);
        $bHead = [];
        if ($this->opcode == 10) { // POng
            $bHead[0] = 137;
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
        // detect ping or pong frame
        $this->opcode = ord($payload[0]) & 15;
        $length = ord($payload[1]) & 127;
        if ($length == 126) {
            $masks = substr($payload, 4, 4);
            $data = substr($payload, 8);
        } else if ($length == 127) {
            $masks = substr($payload, 10, 4);
            $data = substr($payload, 14);
        } else {
            $masks = substr($payload, 2, 4);
            $data = substr($payload, 6, $length); // hgs 30.09.2016
        }
        $text = '';
        $l = strlen($data);
        $m0 = $masks[0];
        $m1 = $masks[1];
        $m2 = $masks[2];
        $m3 = $masks[3];
        for ($i = 0; $i < $l;) {
            $text .= $data[$i++] ^ $m0;
            if ($i < $l) {
                $text .= $data[$i++] ^ $m1;
                if ($i < $l) {
                    $text .= $data[$i++] ^ $m2;
                    if ($i < $l) {
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

        if ($Lines[0] == "php process") {
            $this->Log('Handshake:' . $Buffer);
            $this->Clients[$SocketID]->Headers = 'tcp';
            $this->Clients[$SocketID]->Handshake = true;
            preg_match("/GET (.*) HTTP/i", $Buffer, $reqResource);
            $Headers['get'] = trim($reqResource[1]);
            if (isset($this->allApps[$Headers['get']])) {
                $this->Clients[$SocketID]->app = $this->allApps[$Headers['get']];
            }
            return true;
        }
        $this->Log('Handshake: webClient');
        foreach ($Lines as $Line) {
            if (strpos($Line, ":") !== false) {
                $Header = explode(":", $Line, 2);
                $Headers[strtolower(trim($Header[0]))] = trim($Header[1]);
            } else if (stripos($Line, "get ") !== false) {
                preg_match("/GET (.*) HTTP/i", $Buffer, $reqResource);
                $Headers['get'] = trim($reqResource[1]);
            }
        }
  
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

        $this->Clients[$SocketID]->Headers = 'websocket';
        $this->Clients[$SocketID]->Handshake = true;
        if (isset($this->allApps[$Headers['get']])) {
            $this->Clients[$SocketID]->app = $this->allApps[$Headers['get']];
        }
        return true;
    }

}
