<?php

include __DIR__ . "/../phpClient/websocketCore.php";

class websocketXrpl extends websocketCore {

    function __construct($Address) {

        if (parent::__construct($Address) == false) {
            return;
        }
//       
        $InitialTXLookup = '{
"op": "login",
 "args" : [
{
"apiKey": "985d5b66-57ce-40fb-b714-afc0b9787083",
 "passphrase" : "123456",
 "timestamp" : "1538054050",
 "sign" : "7L+zFQ+CEgGu5rzCj4+BdV2/uUHGqddA9pI6ztsRRPs="
},
 {
"apiKey" : "86126n98-57ce-40fb-b714-afc0b9787083",
 "passphrase" :"123456",
 "timestamp" :"1538054050",
 "sign":"7L+zFQ+CEgGu5rzCj4+BdV2/uUHGqddA9pI6ztsRRPs="
}
]
}';

        $this->writeSocket($InitialTXLookup);

        $resp1 = $this->readSocket();
        echo $resp1;
    }

}

$x = new websocketXrpl("wss://wspap.okx.com:8443/ws/v5/public?brokerId=9999");

