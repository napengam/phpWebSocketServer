function socketWebClient(server, port, app) {
    'uses strict';
    var
            tmp = [], queue = [], uuid, socket = {}, serveros, proto, chunkSize = 6 * 1024,
            socketOpen = false, socketSend = false;
    //******************
    //* figure out what 
    // * protokoll to use
    //******************/
    tmp = server.split('://');
    if (tmp[0] === 'ssl') {
        proto = 'wss://';
    } else {
        proto = 'ws://';
    }
    if (tmp.length > 1) {
        server = tmp[1];
    }

    uuid = generateUUID();
    function init() {

        callbackStatus('Try to connect ...');
        socket = new WebSocket('' + proto + server + ':' + port + app);
        socket.onopen = function () {
            queue = [];
            callbackStatus('Connected');
        };
        socket.onerror = function () {
            if (socketSend === false) {
                callbackStatus('Can not connect to specified server');
            }
            socketSend = false;
            socketOpen = false;
            queue = [];
        };

        socket.onmessage = function (msg) {
            var packet;
            if (msg.data.length === 0 || msg.data.indexOf('pong') >= 0) {
                return;
            }
            packet = JSON.parse(msg.data);
            if (packet.opcode === 'next') {
                //******************
                //* server is ready for next message
                //******************/
                queue.shift();
                if (queue.length > 0) {
                    msg = queue[0];
                    socket.send(msg);
                } else {
                    queue = [];
                }
                return;
            } else if (packet.opcode === 'ready') {
                socketOpen = true;
                socketSend = true;
                serveros = packet.os;
                msg = {'opcode': 'uuid', 'message': uuid};
                sendMsg(msg);
                callbackReady(packet);
                return;
            }
            callbackReadMessage(packet);
        };
        socket.onclose = function () {
            queue = [];
            socketOpen = false;
            socketSend = false;
        };
    }
    function callbackStatus(state) { // dummy callback
        return state;
    }
    function callbackReady(p) { // dummy callback
        return p;
    }
    function callbackReadMessage(p) { // dummy callback
        return p;
    }
    function setCallbackStatus(func) {
        //  overwrite dummy call back with your own func
        callbackStatus = func;
    }
    function setCallbackReady(func) {
        //  overwrite dummy call back with your own func
        callbackReady = func;
    }
    function setCallbackReadMessage(func) {
        //  overwrite dummy call back with your own func
        callbackReadMessage = func;
    }

    function generateUUID() { // Public Domain/MIT
        var d = new Date().getTime();
        if (typeof performance !== 'undefined' && typeof performance.now === 'function') {
            d += performance.now(); //use high-precision timer if available
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (d + Math.random() * 16) % 16 | 0;
            d = Math.floor(d / 16);
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    function sendMsg(msgObj) {
        var i, j, nChunks, msg, sendNow = false;
        if (!socketSend || !socketOpen) {
            return;
        }
        msg = JSON.stringify(msgObj);
        if (msg.length < chunkSize) {
            queue.push(msg);
        } else {
            //********************************************
            //  sending long messages in chunks
            //*******************************************
            if (queue.length === 0) {
                sendNow = true;
            }
            queue.push('bufferON'); //command for the server
            nChunks = Math.floor(msg.length / chunkSize);
            for (i = 0, j = 0; i < nChunks; i++, j += chunkSize) {
                queue.push(msg.slice(j, j + chunkSize));
            }
            if (msg.length % chunkSize > 0) {
                queue.push(msg.slice(j, j + msg.length % chunkSize));
            }
            queue.push('bufferOFF'); //command for the server
        }

        if ((queue.length === 1 || sendNow) && socketOpen) {
            msg = queue[0];
            socket.send(msg);
            sendNow = false;
        }
    }
    function quit() {
        sendMsg({'opcode': 'quit', 'role': 'thisUserRole'});
        socket.close();
        socketOpen = false;
        socketSend = false;
    }
    function isOpen() {
        return socketOpen;
    }


    return {
        'init': init,
        'sendMsg': sendMsg,
        'uuid': function () {
            return uuid;
        }(),
        'quit': quit,
        'isOpen': isOpen,
        'setCallbackReady': setCallbackReady,
        'setCallbackReadMessage': setCallbackReadMessage,
        'setCallbackStatus': setCallbackStatus
    };
}


