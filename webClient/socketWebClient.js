function socketWebClient(server, app) {
    'use strict';

    let queue = [];
    let uuidValue;
    let socket = null;
    const chunkSize = 0 * 1024;  // Define chunk size, currently 0
    let socketOpen = false;
    let socketSend = false;

    function uuid() {
        return uuidValue;
    }

    function init() {
        if (socket !== null) {
            socket.close();
        }

        //********************************************
        //  connect to server at port
        //********************************************
        try {
            socket = new WebSocket(server + app);
            callbackStatus('Try to connect ...');
        } catch (e) {
            socket = null;
            return;
        }

        socket.onopen = function () {
            queue = [];
            callbackStatus('Connected');
        };

        socket.onerror = function () {
            if (!socketSend) {
                callbackStatus('Cannot connect to specified server');
            }
            socketSend = false;
            socketOpen = false;
            queue = [];
        };

        //********************************************
        //  handle message from server
        //********************************************
        socket.onmessage = function (msg) {
            if (msg.data.length === 0 || msg.data.includes('pong')) {
                return;
            }

            const packet = JSON.parse(msg.data);

            switch (packet.opcode) {
                case 'next':
                    // Server is ready for the next message
                    queue.shift();
                    if (queue.length > 0) {
                        const nextMsg = queue[0];
                        socket.send(nextMsg);
                    } else {
                        queue = [];
                    }
                    break;

                case 'ready':
                    // Server is ready; receive UUID from server
                    socketOpen = true;
                    socketSend = true;
                    uuidValue = packet.uuid;
                    callbackReady(packet);
                    break;

                case 'close':
                    // Server has closed the connection
                    socketOpen = false;
                    socketSend = false;
                    callbackStatus('Server closed connection');
                    break;

                default:
                    // Unknown opcode; pass message to external function for handling
                    callbackReadMessage(packet);
                    break;
            }

           
        };

        //********************************************
        //  handle socket close
        //********************************************
        socket.onclose = function () {
            queue = [];
            socketOpen = false;
            socketSend = false;
            callbackClose();
        };
    }

    //********************************************
    //  queue messages to be sent
    //********************************************
    function sendMsg(msgObj) {
        if (!socketSend || !socketOpen) {
            return;
        }

        const msg = JSON.stringify(msgObj);
        let sendNow = false;

        if (msg.length < chunkSize || chunkSize === 0) {
            queue.push(msg);
        } else {
            if (queue.length === 0) {
                sendNow = true;
            }
            queue.push('bufferON');  // Start of large message chunks

            const nChunks = Math.floor(msg.length / chunkSize);
            for (let i = 0, j = 0; i < nChunks; i++, j += chunkSize) {
                queue.push(msg.slice(j, j + chunkSize));
            }

            if (msg.length % chunkSize > 0) {
                queue.push(msg.slice(nChunks * chunkSize));
            }
            queue.push('bufferOFF');  // End of large message chunks
        }

        if ((queue.length === 1 || sendNow) && socketOpen) {
            socket.send(queue[0]);
            sendNow = false;
        }
    }

    //********************************************
    //  Dummy functions; should be set from outside
    //********************************************
    let callbackStatus = function (p) {
        return p;
    };
    let callbackReady = function (p) {
        return p;
    };
    let callbackReadMessage = function (p) {
        return p;
    };
    let callbackClose = function () {
        return '';
    };

    //**************************************************
    //  Functions to set/overwrite dummy functions above
    //**************************************************
    function setCallbackStatus(func) {
        callbackStatus = func;
    }
    function setCallbackReady(func) {
        callbackReady = func;
    }
    function setCallbackReadMessage(func) {
        callbackReadMessage = func;
    }
    function setCallbackClose(func) {
        callbackClose = func;
    }

    //********************************************
    //  Convenience functions for message types
    //********************************************
    function broadcast(msg) {
        sendMsg({'opcode': 'broadcast', 'message': msg});
    }

    function feedback(msg, toUUID) {
        sendMsg({'opcode': 'feedback', 'message': msg, 'uuid': toUUID, 'from': uuid});
    }

    function echo(msg) {
        sendMsg({'opcode': 'echo', 'message': msg});
    }

    function quit() {
        socket.close();
        socketOpen = false;
        socketSend = false;
    }

    function isOpen() {
        return socketOpen;
    }

    //********************************************
    //  Expose functions to the caller
    //********************************************
    return {
        init,
        sendMsg,
        uuid,
        quit,
        isOpen,
        setCallbackStatus,
        setCallbackReady,
        setCallbackReadMessage,
        setCallbackClose,
        broadcast,
        feedback,
        echo
    };
}
