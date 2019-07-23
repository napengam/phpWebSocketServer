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
        <button onclick="xyz();">Talk to others</button>
        <div id="broadcast">
            <b>From others</b><br>&nbsp;
        </div>;
        <script>
            'use strict';
            var sock, uuid;
            sock = socketWebClient(server, port);
            sock.setCallbackReady(xyz);
            sock.setCallbackReadMessage(readMessage);
            sock.init();
            uuid = sock.uuid;

            function readMessage(packet) {
                var obj;
                if (packet.opcode === 'broadcast') {
                    obj = document.getElementById('broadcast');
                    obj.innerHTML += packet.message + '<br>';
                }

            }
            function xyz() {
                sock.sendMsg({'opcode': 'broadcast', 'message': 'hallo11 from :' + uuid});
                sock.sendMsg({'opcode': 'broadcast', 'message': 'hallo22 from :' + uuid});
                sock.sendMsg({'opcode': 'broadcast', 'message': 'hallo33 from :' + uuid});
                sock.sendMsg({'opcode': 'broadcast', 'message': 'hallo44 from :' + uuid});
            }
        </script>
    </body>
</html>
