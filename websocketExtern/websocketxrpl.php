<?php

include __DIR__ . "/../phpClient/websocketCore.php";

class websocketXrpl extends websocketCore {

    function __construct($Address) {

        if (parent::__construct($Address) == false) {
            return;
        }
//        $this->writeSocket(' {"command": "server_info"} ');
//        $resp1 = $this->readSocket();
        //secho $resp1;
        $InitialTXLookup = json_encode(array(
            'id' => 2,
            'command' => "account_tx",
            'account' => "r4DymtkgUAh2wqRxVfdd3Xtswzim6eC6c5",
            'ledger_index_min' => -1,
            'ledger_index_max' => -1,
            'binary' => false,
            'limit' => 50,
            'forward' => true
        ));

        $this->writeSocket($InitialTXLookup);

        $resp1 = $this->readSocket();
        echo $resp1;
        
    }

}

$x = new websocketXrpl("wss://xrplcluster.com");

