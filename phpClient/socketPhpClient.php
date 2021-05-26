<?php

class socketTalk {

    public $uuid, $connected = false, $chunkSize = 6 * 1024;
    private $socketMaster;

    function __construct($Address, $Port, $application = '/', $uu = '') {
        $context = stream_context_create();
        $arr = explode('://', $Address, 2);
        if (count($arr) > 1) {
            if (strncasecmp($arr[0], 'ssl', 3) == 0) {
                stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
                stream_context_set_option($context, 'ssl', 'verify_peer', false);
                stream_context_set_option($context, 'ssl', 'verify_peer_name', false);
            } else {
                $Address = $arr[1]; // just the host
            }
        }
        $errno = 0;
        $errstr = '';
        $this->socketMaster = stream_socket_client("$Address:$Port", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);

        if (!$this->socketMaster) {
            $this->connected = false;
            return;
        }
        $this->connected = true;
        fwrite($this->socketMaster, $this->setHandshake($Address));
        $buff = fread($this->socketMaster, 1024);
        if (!$this->getHandshake($buff)) {
            $this->silent();
            return;
        }
        $buff = fread($this->socketMaster, 1024); // wait for ACK       
        $buff = $this->decodeFromServer($buff);
        $json = json_decode($buff);
        if ($json->opcode != 'ready') {
            $this->connected = false;
        }
        $this->fromUUID = $json->uuid; // assigned by server to this script
        if ($uu != '') {
            $this->uuid = $uu;
        }
    }

    final function broadcast($message) {
        $this->talk(['opcode' => 'broadcast', 'message' => $message]);
    }

    final function feedback($message) {
        if ($this->uuid) {
            $this->talk([
                'opcode' => 'feedback',
                'uuid' => $this->uuid,
                'message' => $message,
                'fromUUID' => $this->fromUUID]);
        }
    }

    final function talk($msg) {
        if ($this->connected === false) {
            return;
        }
        $json = json_encode((object) $msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
        $len = mb_strlen($json);
        if ($len > $this->chunkSize && $this->chunkSize > 0) {
            $nChunks = floor($len / $this->chunkSize);
            if ($this->writeWait('bufferON')) {
                for ($i = 0, $j = 0; $i < $nChunks; $i++, $j += $this->chunkSize) {
                    if ($this->writeWait(mb_substr($json, $j, $j + $this->chunkSize)) === false) {
                        break;
                    }
                }
            }
            if ($len % $this->chunkSize > 0) {
                $this->writeWait(mb_substr($json, $j, $j + $len % $this->chunkSize));
            }
            $this->writeWait('bufferOFF');
        } else {
            $this->writeWait($json);
        }
    }

    final function silent() {
        if ($this->connected) {
            $this->connected = false;
            fclose($this->socketMaster);
        }
    }

    final function writeWait($m) {
        if ($this->connected === false) {
            return false;
        }
        fwrite($this->socketMaster, $this->encodeForServer($m));
        $buff = $this->decodeFromServer(fread($this->socketMaster, 1024)); // wait for ACK
        $ack = json_decode($buff);
        if ($ack->opcode != 'next') {
            $this->silent();
            return false;
        }
        return true;
    }

    function setHandshake($server) {

        $this->key = random_bytes(16);
        $key = base64_encode($this->key);

        /*
         * ***********************************************
         * we expect this Token from the 
         * server in its responds
         * ***********************************************
         */

        $sah1 = sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11");
        for ($i = 0, $Token = ""; $i < 20; $i++) {
            $Token .= chr(hexdec(substr($sah1, $i * 2, 2)));
        }
        $this->expectedToken = base64_encode($Token);

        return
                "GET /php HTTP/1.1\r\n
        Host: $server\r\n
        Upgrade: websocket\r\n
        Connection: Upgrade\r\n
        Sec-WebSocket-Key: $key\r\n
        Origin: \r\n     
        Sec-WebSocket-Version: 13\r\n";
    }

    function getHandshake($Buffer) {
        $Headers = [];
        $Lines = explode("\n", $Buffer);
        foreach ($Lines as $Line) {
            if (strpos($Line, ":") !== false) {
                $Header = explode(":", $Line, 2);
                $Headers[strtolower(trim($Header[0]))] = trim($Header[1]);
            } else if (stripos($Line, "HTTP/") !== false) {
                $Headers['101'] = trim($Line);
            }
        }
        if ($Headers['101'] != "HTTP/1.1 101 Switching Protocols") {
            return false;
        }
        if ($Headers['upgrade'] != 'websocket') {
            return false;
        }
        if ($Headers['connection'] != 'Upgrade') {
            return false;
        } if ($Headers['sec-websocket-accept'] != $this->expectedToken) {
            return false;
        }
        return true;
    }

    private function encodeForServer($M) {
        $L = strlen($M);
        $bHead = [];
        $bHead[0] = 129; // 0x1 text frame (FIN + opcode)
        $masks = random_bytes(4);
        if ($L <= 125) {
            $bHead[1] = $L | 128;
        } else if ($L >= 126 && $L <= 65535) {
            $bHead[1] = 126 | 128;
            $bHead[2] = ( $L >> 8 ) & 255;
            $bHead[3] = ( $L ) & 255;
        } else {
            $bHead[1] = 127 | 128;
            $bHead[2] = ( $L >> 56 ) & 255;
            $bHead[3] = ( $L >> 48 ) & 255;
            $bHead[4] = ( $L >> 40 ) & 255;
            $bHead[5] = ( $L >> 32 ) & 255;
            $bHead[6] = ( $L >> 24 ) & 255;
            $bHead[7] = ( $L >> 16 ) & 255;
            $bHead[8] = ( $L >> 8 ) & 255;
            $bHead[9] = ( $L ) & 255;
        }
        $m0 = $masks[0];
        $m1 = $masks[1];
        $m2 = $masks[2];
        $m3 = $masks[3];
        for ($i = 0, $text = ''; $i < $L;) {
            $text .= $M[$i++] ^ $m0;
            if ($i < $L) {
                $text .= $M[$i++] ^ $m1;
                if ($i < $L) {
                    $text .= $M[$i++] ^ $m2;
                    if ($i < $L) {
                        $text .= $M[$i++] ^ $m3;
                    }
                }
            }
        }
        return (implode(array_map("chr", $bHead)) . $masks . $text);
    }

    private function decodeFromServer($payload) {
        // detect ping or pong frame, or fragments

        $this->fin = ord($payload[0]) & 128;
        $this->opcode = ord($payload[0]) & 15;
        $length = ord($payload[1]) & 127;

        if ($length <= 125) {
            $poff = 2;
        } else if ($length == 126) {
            $l0 = ord($payload[2]) << 8;
            $l1 = ord($payload[3]);
            $length = ($l0 | $l1);
            $poff = 4;
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
            $poff = 10;
        }
        $data = substr($payload, $poff, $length);

        return $data;
    }

}
