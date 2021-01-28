/* global server, port */
window.addEventListener('load', startGUI, false);
function startGUI() {
    'use strict';
    var sock, uuid, i, longString = '';

    //********************************************
    //  Prepare the socket ecosystem :-)
    //*******************************************

    sock = socketWebClient(server, port, '/web');
    sock.setCallbackReady(ready);
    sock.setCallbackReadMessage(readMessage);
    sock.setCallbackStatus(sockStatus);
    sock.setCallbackClose(closeSocket);

    sock.init();
    uuid = sock.uuid;

    //********************************************
    //  create a long message
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
    function closeSocket() {
        //*******************************
        // report connection status
        //*******************************
        document.getElementById('connect').innerHTML = 'Server is gone; closed socket';
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

}
