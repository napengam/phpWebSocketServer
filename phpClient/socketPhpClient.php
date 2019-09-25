<?php

class socketTalk {

    public $uuid, $connected = false, $serveros = 'linux';
    private $socketMaster;

    function __construct($Address, $Port) {
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
        fwrite($this->socketMaster, "php process\n\n");
        $buff = fread($this->socketMaster, 256); // wait for ACK
        $param = json_decode($buff);
    }

    final function talk($msg) {
        if ($this->connected) {
            $json = json_encode((object) $msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
            fwrite($this->socketMaster, $json);
            $buff = fread($this->socketMaster, 256); // wait for ACK
        }
    }

    final function silent() {
        if ($this->connected) {
            fclose($this->socketMaster);
        }
    }

}
