<?php

include __DIR__ . "/websocketCore.php";

class websocketPhp extends websocketCore {

    public $uuid = '', $connected = false, $chunkSize = 0; // 6 * 1024;

    //private $socketMaster;

    function __construct($Address) {

        if (parent::__construct($Address)) {

            $buff = fread($this->socketMaster, 1024); // wait for ACK       
            $buff = $this->decodeFromServer($buff);
            $json = json_decode($buff);
            if ($json->opcode != 'ready') {
                $this->connected = false;
                return;
            }
            $this->fromUUID = $json->uuid; // assigned by server to this script
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

    final function writeWait($m) {
        if ($this->connected === false) {
            return false;
        }
        $this->writeSocket($m);
        $buff = $this->readSocket(); // wait for ACK
        $ack = json_decode($buff);
        if ($ack->opcode != 'next') {
            $this->silent();
            return false;
        }
        return true;
    }

}
