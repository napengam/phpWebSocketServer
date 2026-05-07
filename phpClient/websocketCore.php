<?php

class websocketCore {

    public $prot,
            $connected = false,
            $ident,
            $socketMaster,
            $key,
            $expectedToken,
            $errorHandshake,
            $fin,
            $opcode,
            $length,
            $timeout = 2;
    private $buffer = '';

    function __construct($Address, $ident = '') {
        $this->ident = $ident;
        $context = stream_context_create();

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

        [$host, $app] = explode('/', $Address, 2) + [null, '/'];
        $app = '/php';

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
            return false;
        }

        stream_set_timeout($this->socketMaster, $this->timeout);
        stream_set_blocking($this->socketMaster, true);

        return true;
    }

    final function writeSocket($message) {
        if ($this->connected) {
            fwrite($this->socketMaster, $this->encodeForServer($message));
        }
    }

    private function readBytes($length) {
        while (strlen($this->buffer) < $length) {
            $chunk = fread($this->socketMaster, 8192);

            if ($chunk === false || $chunk === '') {
                return false;
            }

            $this->buffer .= $chunk;
        }

        $data = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, $length);

        return $data;
    }

    private function decodeFrame() {
        $header = $this->readBytes(2);
        if ($header === false) {
            return false;
        }
        $b1 = ord($header[0]);
        $b2 = ord($header[1]);

        $this->fin = ($b1 >> 7) & 1;
        $this->opcode = $b1 & 0x0F;

        $masked = ($b2 >> 7) & 1;
        $length = $b2 & 0x7F;

        if ($length === 126) {
            $ext = $this->readBytes(2);
            if ($ext === false) {
                return false;
            }
            $length = unpack('n', $ext)[1];
        } elseif ($length === 127) {
            $ext = $this->readBytes(8);
            if ($ext === false) {
                return false;
            }
            $arr = unpack('J', $ext);
            $length = $arr[1];
        }

        $maskKey = '';
        if ($masked) {
            $maskKey = $this->readBytes(4);
            if ($maskKey === false) {
                return false;
            }
        }

        $payload = $this->readBytes($length);
        if ($payload === false) {
            return false;
        }

        if ($masked) {
            for ($i = 0; $i < $length; $i++) {
                $payload[$i] = $payload[$i] ^ $maskKey[$i % 4];
            }
        }

        $this->length = $length;

        return $payload;
    }

    final function readSocketAll() {
        if ($this->connected === false) {
            return '';
        }

        $message = '';

        do {
            $payload = $this->decodeFrame();

            if ($payload === false) {
                return '';
            }

            if ($this->opcode === 8) {
                $this->silent();
                return '';
            }

            if ($this->opcode === 9) {
                $this->sendPong($payload);
                continue;
            }

            if ($this->opcode === 10) {
                continue;
            }

            $message .= $payload;
        } while ($this->fin === 0);

        return $message;
    }

    private function sendPong($payload) {
        $frame = chr(0x8A) . chr(strlen($payload)) . $payload;
        fwrite($this->socketMaster, $frame);
    }

    private function silent() {
        if ($this->socketMaster) {
            fclose($this->socketMaster);
        }
        $this->connected = false;
    }

    private function setHandshake($host, $path) {
        $this->key = base64_encode(random_bytes(16));

        return "GET $path HTTP/1.1\r\n" .
                "Host: $host\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Key: {$this->key}\r\n" .
                "Sec-WebSocket-Version: 13\r\n\r\n";
    }

    private function getHandshake($response) {
        if (strpos($response, '101') === false) {
            $this->errorHandshake = $response;
            return false;
        }
        return true;
    }

    private function encodeForServer($payload) {
        $length = strlen($payload);
        $frame = chr(0x81);

        if ($length <= 125) {
            $frame .= chr(0x80 | $length);
        } elseif ($length <= 65535) {
            $frame .= chr(0x80 | 126) . pack('n', $length);
        } else {
            $frame .= chr(0x80 | 127) . pack('J', $length);
        }

        $mask = random_bytes(4);
        $frame .= $mask;

        for ($i = 0; $i < $length; $i++) {
            $frame .= $payload[$i] ^ $mask[$i % 4];
        }

        return $frame;
    }
}
