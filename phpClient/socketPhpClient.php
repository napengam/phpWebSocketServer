<?php

class socketTalk {

    public $uuid, $connected = false, $serveros = 'linux', $chunkSize = 8 * 1024;
    private $socketMaster;

    function __construct($Address, $Port, $application = '/') {
        $secure = false;
        $arr = explode('://', $Address);
        if (count($arr) > 1) {
            if (strncasecmp($arr[0], 'ssl', 3) == 0) {
                $secure = true;
            } else {
                $Address = $arr[1]; // just the host
            }
        }
        $context = stream_context_create();
        if ($secure) {
            $context = stream_context_create();
            stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
            stream_context_set_option($context, 'ssl', 'verify_peer_name', false);
        }
        $this->socketMaster = stream_socket_client("$Address:$Port", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);

        if (!$this->socketMaster) {
            $this->connected = false;
            return;
        }
        $this->connected = true;
        fwrite($this->socketMaster, "php process\nGET $application HTTP/1.1\n\n");
        $buff = fread($this->socketMaster, 256); // wait for ACK
        $param = json_decode($buff);
    }

    final function talk($msg) {
        if ($this->connected) {
            $json = json_encode((object) $msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
            $len = mb_strlen($json);
            if ($len > $this->chunkSize) {
                $nChunks = floor($len / $this->chunkSize);
                fwrite($this->socketMaster, 'bufferON');
                $buff = fread($this->socketMaster, 256); // wait for ACK

                for ($i = 0, $j = 0; $i < $nChunks; $i++, $j += $this->chunkSize) {
                    fwrite($this->socketMaster, mb_substr($json, $j, $j + $this->chunkSize));
                    $buff = fread($this->socketMaster, 256); // wait for ACK
                }
                fwrite($this->socketMaster, mb_substr($json, $j, $j + $len % $this->chunkSize));
                $buff = fread($this->socketMaster, 256); // wait for ACK
                fwrite($this->socketMaster, 'bufferOFF');
                $buff = fread($this->socketMaster, 256); // wait for ACK
            } else {
                fwrite($this->socketMaster, $json);
                $buff = fread($this->socketMaster, 256); // wait for ACK 
            }
        }
    }

    final function silent() {
        if ($this->connected) {
            fclose($this->socketMaster);
        }
    }

}
