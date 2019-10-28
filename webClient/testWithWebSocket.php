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
        <b><span id='connect'>not connected</span></b><br>
        <button id="ajax" >CALL Backend via AJAX</button><br>
        Here you will see feedback from backend : <b><span id='feedback'></span> </b>
        <hr>
        <button id="ready" >Talk to others; my UUID=<b><span id='uuid'></span></b> </button>
        <div id="broadcast">
            <b>From others</b><br>
        </div>;
        <script>
            window.addEventListener('load', function () {
                'use strict';
                var sock, uuid, i, longString = '';

                //********************************************
                //  Prepare the socket ecosystem :-)
                //*******************************************

                sock = socketWebClient(server, port, '/web');
                sock.setCallbackReady(ready);
                sock.setCallbackReadMessage(readMessage);
                sock.setCallbackStatus(sockStatus);
                sock.init();
                uuid = sock.uuid;

                //********************************************
                //  cretae a long message
                //*******************************************

                for (i = 0; i < 16 * 1024; i++) {
                    longString += 'X';
                }

                function sockStatus(m) {
                    //*******************************
                    // report connection status
                    //*******************************
                    document.getElementById('connect').innerHTML = m;
                }

                function readMessage(packet) {
                    //*******************************
                    // respond to messages from server
                    //*******************************
                    var obj;
                    if (packet.opcode === 'broadcast') {
                        obj = document.getElementById('broadcast');
                        obj.innerHTML += packet.message + '<br>';
                    } else if (packet.opcode === 'feedback') {
                        obj = document.getElementById('feedback');
                        obj.innerHTML = packet.message;
                    }
                }
                function ready() {
                    // ***********************************************
                    //   test if messages apear in same order as send
                    // no message is lost and very long message is buffered
                    // ***********************************************
                    sock.sendMsg({'opcode': 'broadcast', 'message': 'hallo11 from :' + uuid});
                    sock.sendMsg({'opcode': 'broadcast', 'message': 'hallo22 from :' + uuid});
                    sock.sendMsg({'opcode': 'broadcast', 'message': 'hallo33 from :' + uuid});
                    sock.sendMsg({'opcode': 'broadcast', 'message': 'hallo44 from :' + uuid});
                    sock.sendMsg({'opcode': 'broadcast', 'message': longString + uuid});
                }

                function triggerAJAX() {
                    //****************************************
                    // start dummy backend script
                    //****************************************
                    var req;
                    req = new XMLHttpRequest();
                    req.open("POST", '../phpClient/simulateBackend.php');
                    req.setRequestHeader("Content-Type", "application/json");
                    req.send(JSON.stringify({'uuid': uuid}));
                }
                //********************************************
                //  instrument the buttons
                //*******************************************

                document.getElementById('ready').onclick = ready;
                document.getElementById('ajax').onclick = triggerAJAX;
                document.getElementById('uuid').innerHTML = uuid;

            }, false);
        </script>
    </body>
</html>
