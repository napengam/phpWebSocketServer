/* global server, port */
window.addEventListener('load', startGUI, false);
function startGUI() {
    'use strict';
    var sock, uuid, i, longString = '';

    //********************************************
    //  Prepare the socket ecosystem :-)
    //*******************************************

    sock = socketWebClient(server, '/web');
    sock.setCallbackReady(ready);
    sock.setCallbackReadMessage(readMessage);
    sock.setCallbackStatus(sockStatus);
    sock.setCallbackClose(closeSocket);

    sock.init();


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
            obj.innerHTML = packet.fromUUID + '---' + packet.message;
        } else if (packet.opcode === 'echo') {
            obj = document.getElementById('echomsg');
            obj.innerHTML = packet.message;
        }
    }
    function ready() {
        // ***********************************************
        // we have now the uuid from the server and can start
        // ***********************************************
        uuid = sock.uuid();
        document.getElementById('uuid').innerHTML = uuid;
        talkToOthers();
    }

    function talkToOthers() {
        // ***********************************************
        //  test if messages apear in other webclients  in same order as send
        // no message is lost and very long message is buffered
        // ****************************************************
        sock.broadcast(`hallo11 from :${uuid}`);
        sock.broadcast(`hallo22 from :${uuid}`);
        sock.broadcast(`hallo33 from :${uuid}`);
        sock.broadcast(`hallo44 from :${uuid}`);
        sock.broadcast(longString + uuid);
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
    function echo() {
        sock.echo(`ECHO from :${uuid}`);
    }
    //********************************************
    //  instrument the buttons
    //*******************************************

    document.getElementById('open').onclick = sock.init;
    document.getElementById('close').onclick = sock.quit;
    document.getElementById('ready').onclick = talkToOthers;
    document.getElementById('ajax').onclick = triggerAJAX;
    document.getElementById('echo').onclick = echo;
    document.getElementById('uuid').innerHTML = uuid;


}
