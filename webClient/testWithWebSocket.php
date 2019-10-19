<!DOCTYPE html>
<!--
To change this license header, choose License Headers in Project Properties.
To change this template file, choose Tools | Templates
and open the template in the editor.
-->
<html>
    <head>
        <meta charset="UTF-8">
        <title></title>
        <script src="socketWebClient.js"></script>
    </head>
    <body>
        <?php
        include '../include/adressPort.inc.php';
        echo "<script>"
        . "server='$Address';"
        . "port='$Port';"
        . "</script>";
        ?>
        <button id="ready" >Talk to others; my UUID=<b><span id='uuid'></span></b> </button>
        <div id="broadcast">
            <b>From others</b><br>
        </div>;
        <script>
            !function () {
                'use strict';
                var sock, uuid, i, longString = '';

                sock = socketWebClient(server, port, '/web');
                sock.setCallbackReady(ready);
                sock.setCallbackReadMessage(readMessage);
                sock.init();
                uuid = sock.uuid;
                for (i = 0; i < 16*1024; i++) {
                    longString += 'X';
                }
                function readMessage(packet) {
                    var obj;
                    if (packet.opcode === 'broadcast') {
                        obj = document.getElementById('broadcast');
                        obj.innerHTML += packet.message + '<br>';
                    }
                }
                function ready() {
                    sock.sendMsg({'opcode': 'broadcast', 'message': 'hallo11 from :' + uuid});
                    sock.sendMsg({'opcode': 'broadcast', 'message': 'hallo22 from :' + uuid});
                    sock.sendMsg({'opcode': 'broadcast', 'message': 'hallo33 from :' + uuid});
                    sock.sendMsg({'opcode': 'broadcast', 'message': 'hallo44 from :' + uuid});
                    sock.sendMsg({'opcode': 'broadcast', 'message': longString + uuid});
                }
                document.getElementById('ready').onclick = ready;
                document.getElementById('uuid').innerHTML = uuid;
            }();
        </script>
    </body>
</html>
