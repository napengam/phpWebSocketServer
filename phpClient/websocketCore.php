<?php

class websocketCore {

    public $prot, $connected = false, $firstFragment = true, $finBit = true;

    //private $socketMaster;

    function __construct($Address, $ident = '') {
        $context = stream_context_create();
        $this->ident = $ident;
        /*
         * ***********************************************
         * extract protokol and set default port
         * ***********************************************
         */
        $arr = explode('://', $Address, 2);
        $prot = '';
        if (count($arr) > 1) {
            $p = strtolower($arr[0]);
            if ($p === 'ssl' || $p === 'wss') {
                stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
                stream_context_set_option($context, 'ssl', 'verify_peer', false);
                stream_context_set_option($context, 'ssl', 'verify_peer_name', false);
                $px = '443';
                $prot = 'ssl://';
                $Address = $arr[1];
            } else {
                $prot = 'tcp://';
                $Address = $arr[1]; // just the host
                $px = '80';
            }
        } else {
            $prot = 'tcp://';
            $px = '80';
        }
        /*
         * ***********************************************
         * extract endpoint $app, default= '/'
         * ***********************************************
         */
        $app = '/';
        $arr = explode('/', $Address, 2);
        if (count($arr) > 1) {
            $Address = $arr[0];
            $app = '/' . $arr[1];
        }
        /*
         * ***********************************************
         * extract port from $Address
         * ***********************************************
         */
        $Port = '';
        $arr = explode(':', $Address);
        if (count($arr) > 1) {
            $Address = $arr[0];
            $Port = $arr[1];
        }

        $this->prot = $prot;
        if ($Port) {
            $Port = ":$Port";
        } else {
            $Port = ":$px";
        }
        $errno = 0;
        $errstr = '';
        $this->socketMaster = stream_socket_client("$prot$Address$Port", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);

        if (!$this->socketMaster) {
            $this->connected = false;
            return false;
        }
        $this->connected = true;
        fwrite($this->socketMaster, $this->setHandshake($Address, $app));
        $buff = fread($this->socketMaster, 1024);
        if (!$this->getHandshake($buff)) {
            $this->silent();
            echo $this->errorHandshake;
            return false;
        }

        return true;
    }

    final function writeSocket($message) {
        if ($this->connected) {
            fwrite($this->socketMaster, $this->encodeForServer($message));
        }
    }

    final function readSocket() {

        if ($this->connected === false) {
            return;
        }
        $buff = [];
        $i = 0;
        do { // probaly reading fragements
            $buff[$i] = $this->decodeFromServer(fread($this->socketMaster, 8192));

            if ($this->opcode == 9) { // ping frame
                $this->frame[0] = 138; // send back as pong
                fwrite($this->socketMaster, $this->frame, strlen($this->frame));
                $this->fin = false; // keep in loop
                continue;
            } else if ($this->opcode == 10) { // pong frame ignore
                $this->fin = false; // keep in loop
                continue;
            } else if ($this->opcode == 8) { // close frame
                $this->silent();
                return '';
            }

            $this->length -= strlen($buff[$i]);
            $i++;
            while ($this->length > 0) {
                $buff[$i] = fread($this->socketMaster, 8192);
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

        /*
         * ***********************************************
         * we expect $this->expectedToken  from the 
         * server in its responds
         * ***********************************************
         */

        $sah1 = sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11");
        for ($i = 0, $Token = ""; $i < 20; $i++) {
            $Token .= chr(hexdec(substr($sah1, $i * 2, 2)));
        }
        $this->expectedToken = base64_encode($Token);

        if ($this->prot == 'ssl://') {
            $prot = "https://";
        } else {
            $prot = "http://";
        }

        $req = [];
        $req[] = "GET $app HTTP/1.1";
        $req[] = "Host: $server";
        $req[] = "Upgrade: websocket";
        $req[] = "Connection: Upgrade";
        $req[] = "Sec-WebSocket-Key: $key";
        $req[] = "Origin: $prot$server ";
        $req[] = "Sec-WebSocket-Version: 13";
        $req[] = "Client-Type: php";  // hgs private , not part of RCF7455
        $req[] = "Ident: $this->ident";  // hgs private , not part of RCF7455

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
        } if ($Headers['sec-websocket-accept'] != $this->expectedToken) {
            return false;
        }
        $this->errorHandshake = '';
        return true;
    }

    final function encodeForServer($M) {
        $L = strlen($M);
        $bHead = [];
        if ($L == 0) {
            $bHead[0] = 136; // close frame if message length = 0
        } else {
            if ($this->finBit) {
                if ($this->firstFragment) {
                    $bHead[0] = 129; // 0x1 text frame (FIN + opcode)#
                } else {
                    $bHead[0] = 128; // final fragment
                    $this->firstFragment = true;
                }
            } else {
                if ($this->firstFragment) {
                    $bHead[0] = 1;  // first text fragment
                    $this->firstFragment = false;
                } else {
                    $bHead[0] = 0; // nextfragemnt
                }
            }
        }
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
        $text = '';
        for ($i = 0, $text = ''; $i < $L;
        ) {
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

    final function decodeFromServer($frame) {
// detect ping or pong frame, or fragments

        $this->fin = ord($frame[0]) & 128;
        $this->opcode = ord($frame[0]) & 15;
        $this->frame = $frame;
        $length = ord($frame[1]) & 127;

        if ($length <= 125) {
            $poff = 2;
        } else if ($length == 126) {
            $l0 = ord($frame[2]) << 8;
            $l1 = ord($frame[3]);
            $length = ($l0 | $l1);
            $poff = 4;
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

            $poff = 10;
        }
        $this->length = $length;
        $data = substr($frame, $poff, $length);

        return $data;
    }

}
