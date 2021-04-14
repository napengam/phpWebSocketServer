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
        fwrite($this->socketMaster, "php process\nGET $application HTTP/1.1\n");
        $buff = fread($this->socketMaster, 256); // wait for ACK
        $json = json_decode($buff);
        if ($json->opcode != 'ready') {
            $this->connected = false;
        }
        if ($uu != '') {
            $this->uuid = $uu;
        }
    }

    final function broadcast($message) {
        $this->talk(['opcode' => 'broadcast', 'message' => $message]);
    }

    final function feedback($message) {
        if ($this->uuid) {
            $this->talk(['opcode' => 'feedback', 'uuid' => $this->uuid, 'message' => $message]);
        }
    }

    final function talk($msg) {
        if ($this->connected === false) {
            return;
        }
        $json = json_encode((object) $msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
        $len = mb_strlen($json);
        if ($len > $this->chunkSize) {
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
        fwrite($this->socketMaster, $m);
        $buff = fread($this->socketMaster, 256); // wait for ACK
        $ack = json_decode($buff);
        if ($ack->opcode != 'next') {
            $this->silent();
            return false;
        }
        return true;
    }

}
