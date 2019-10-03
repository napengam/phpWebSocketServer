<?php

trait coreFunc {

    public function Encode($M) {
        // inspiration for Encode() method : 
        // http://stackoverflow.com/questions/8125507/how-can-i-send-and-receive-websocket-messages-on-the-server-side
        $L = strlen($M);
        $bHead = [];
        $bHead[0] = 129; // 0x1 text frame (FIN + opcode)
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
        for ($i = 0; $i < strlen($data); ++$i) {
            $text .= $data[$i] ^ $masks[$i % 4];
        }
        return $text;
    }

    public function Log($M, $exit = false) {

        if ($this->logToFile) {
            $M = "[" . date(DATE_RFC1036, time()) . "] - $M \r\n";
            file_put_contents($this->logFile, $M, FILE_APPEND);
        }
        if ($this->logToDisplay) {
            $M = "[" . date(DATE_RFC1036, time()) . "] - $M \r\n";
            echo $M;
        }
        if ($exit) {
            exit;
        }
    }

    protected function addClient($Socket) {
        $index = intval($Socket);
        $this->Clients[$index] = (object) ['ID' => $index, 'uuid' => '', 'Headers' => null, 'Handshake' => null, 'timeCreated' => null];
        $this->Sockets[$index] = $Socket;
        return $index;
    }

    protected function getClient($Socket) {
        return $this->Clients[intval($Socket)];
    }

    protected function Handshake($Socket, $Buffer) {
        $this->Log('Handshake:' . $Buffer);
        $addHeader = [];
        $SocketID = intval($Socket);
        $Lines = explode("\n", $Buffer);
        if ($Lines[0] == "php process") {
            $this->Clients[$SocketID]->Headers = 'tcp';
            $this->Clients[$SocketID]->Handshake = true;
            return true;
        }

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

        if (!isset($Headers['host']) ||
                !isset($Headers['sec-websocket-key']) ||
                (!isset($Headers['upgrade']) || strtolower($Headers['upgrade']) != 'websocket') ||
                (!isset($Headers['connection']) || strpos(strtolower($Headers['connection']), 'upgrade') === FALSE)) {
            $addHeader[] = "HTTP/1.1 400 Bad Request";
        }
        if (!isset($Headers['sec-websocket-version']) || strtolower($Headers['sec-websocket-version']) != 13) {
            $addHeader[] = "HTTP/1.1 426 Upgrade Required\r\nSec-WebSocketVersion: 13";
        }
        if (!isset($Headers['get'])) {
            $addHeader[] = "HTTP/1.1 405 Method Not Allowed\r\n\r\n";
        }
        if (count($addHeader) > 0) {
            $addh = implode("\r\n", $addHeader);
            fwrite($Socket, $addh, strlen($addh));

            $this->onError($SocketID, "Handshake aborted - [" . trim($addh) . "]");
            $this->Close($Socket);
            return false;
        }
        $Token = "";
        $sah1 = sha1($Headers['sec-websocket-key'] . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11");
        for ($i = 0; $i < 20; $i++) {
            $Token .= chr(hexdec(substr($sah1, $i * 2, 2)));
        }
        $Token = base64_encode($Token) . "\r\n";
        $addHeaderOk = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $Token\r\n";
        fwrite($Socket, $addHeaderOk, strlen($addHeaderOk));

        $this->Clients[$SocketID]->Headers = 'websocket';
        $this->Clients[$SocketID]->Handshake = true;
        return true;
    }

    /*
     * ***********************************************
     * for future use
     * ***********************************************
     */

    private function optAssign($opt) {

        foreach ((object) $this->stdOpt as $key => $defaultValue) {
            if ($opt->{$key}) {
                continue;
            }
            $opt->{$key} = $defaultValue;
        }
        return $opt;
    }

    private function getStdOpt() {
        return (object)
                [
                    'address' => '',
                    'port' => '',
                    'certKey' => '',
                    'certPath' => '',
                    'logFile' => '',
                    'logtoFile' => false,
                    'logToConsol' => true
        ];
    }

}
