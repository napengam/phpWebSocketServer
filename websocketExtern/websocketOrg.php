<?php

include __DIR__ . "/../phpClient/websocketCore.php";

class websocketOrg extends websocketCore {

    function __construct($Address) {

        if (parent::__construct($Address) == false) {
            return;
        }

        // 1. Read the server's automatic initial welcome frame first!
        $welcomeMsg = $this->readSocket();
        echo "<b>Server Greeting:</b> " . htmlspecialchars($welcomeMsg) . "<br><br>";

        // 2. Send individual messages
        $this->finBit = true;

        $this->sendMessage("Hello");
        $this->sendMessage("from");
        $this->sendMessage("PHP unfragmented");
    }

    private function sendMessage($text) {
        echo "<b>Sending:</b> " . htmlspecialchars($text) . "<br>";

        $this->writeSocket($text);

        // Ensure stream buffer flushes immediately
        if (is_resource($this->socketMaster)) {
            fflush($this->socketMaster);
        }

        // Give the network a brief moment to respond (optional if readSocket blocks)
        usleep(100000); // 100ms

        $respo = $this->readSocket();
        echo "<b>Received:</b> " . htmlspecialchars($respo) . "<br><br>";
    }
}

$x = new websocketOrg("wss://echo.websocket.org");
