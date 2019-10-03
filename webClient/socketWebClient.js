function socketWebClient(server, port, app) {
    'uses strict';
    var
            tmp = [], queue = [], uuid, socket = {}, serveros, proto,
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
        socket = new WebSocket('' + proto + server + ':' + port + app);

        socket.onopen = function () {
            queue = [];
        };
        socket.onerror = function () {
            socketSend = false;
            socketOpen = true;
        };
        socket.onmessage = function (msg) {
            var packet;
            if (msg.data.length === 0 || msg.data.indexOf('pong') >= 0) {
                return;
            }
            packet = JSON.parse(msg.data);

            if (packet.opcode === 'next' && packet.uuid === uuid) {
                //******************
                //* server is ready for next message
                //******************/
                queue.shift();
                if (queue.length > 0) {
                    msg = queue[0];
                    msg = JSON.stringify(msg);
                    socket.send(msg);
                }
                return;
            } else if (packet.opcode === 'ready') {
                socketOpen = true;
                socketSend = true;
                serveros = packet.os;
                msg = {'opcode': 'uuid', 'message': uuid};
                msg = JSON.stringify(msg);
                socket.send(msg);
                callbackReady(packet);
                return;
            }
            callbackReadMessage(packet);
        };
        socket.onclose = function () {
            socketOpen = false;
            socketSend = false;
        };

    }


    function callbackReady(p) {
        //*
        // ******************************************
        // * dummy call back
        // ******************************************
        // */
        return p;
    }
    function callbackReadMessage(p) {
        ///*
        // ******************************************
        //* dummy call back
        // ******************************************
        // */
        return p;
    }
    function setCallbackReady(func) {
        //*
        // * overwrite dummy call back with your own
        // * function func
        //
        callbackReady = func;
    }
    function setCallbackReadMessage(func) {
        // 
        // * overwrite dummy call back with your own
        // * function func
        // */
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

    function sendMsg(msg) {

        if (!socketSend) {
            return;
        }
        try {
            // queue messages until server asks for next message
            if (socketOpen) {
                queue.push(msg);
            }
            if (queue.length === 1 && socketOpen) {
                msg = queue[0];
                socket.send(JSON.stringify(msg));
            }
        } catch (ex) {
            socketSend = false;
            alert('socket error: ' + ex);
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
        'setCallbackReadMessage': setCallbackReadMessage
    };
}


