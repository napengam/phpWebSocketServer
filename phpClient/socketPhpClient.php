<?php

class socketTalk {

    public $uuid, $connected = false, $serveros = 'linux';
    private $socketMaster, $useStream = true;

    function __construct($Address, $Port) {
        $context = '';
        if (stripos(" $Address", 'ssl://')) {
            $context = stream_context_create();
            stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
            stream_context_set_option($context, 'ssl', 'verify_peer_name', false);
            $this->socketMaster = stream_socket_client("$Address:$Port", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
            $connect = true;
        } else {
            $this->socketMaster = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            $connect = socket_connect($this->socketMaster, $Address, $Port);
            $this->useStream = false;
        }
        if (!$this->socketMaster || !$connect) {
            echo $errstr;
            return;
        }

        $this->connected = true;
        $buff = $this->fwriteSS($this->socketMaster, "php process\n\n");
        $param = json_decode($buff);
        $this->serveros = $param->os;
    }

    final function talk($msg) {
        if ($this->connected) {
            $json = json_encode((object) $msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
            /*
             * *****************************************
             * send data now
             * *****************************************
             */
            $what = $this->fwriteSS($this->socketMaster, $json);
        }
    }

    final function silent() {
        if ($this->connected) {
            if ($this->useStream) {
                fclose($this->socketMaster);
            } else {
                socket_close($this->socketMaster);
            }
        }
    }

    private final function freadSS($SOS, $len) {
        if ($this->connected == false) {
            return;
        }
        if ($this->useStream) {
            $buff = fread($SOS, $len);
        } else {
            $buff = socket_read($SOS, $len);
        }
        return $buff;
    }

    private final function fwriteSS($SOS, $buff) {
        if ($this->connected == false) {
            return;
        }
        if ($this->useStream) {
            fwrite($SOS, $buff);
        } else {
            socket_write($SOS, $buff, strlen($buff));
            $buff = socket_read($SOS, 1024);
            return $buff;
        }
    }

}
