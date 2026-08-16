function socketWebClient(server, app) {
    'use strict';

    let queue = [];
    let uuidValue = null;
    let socket = null;
    const chunkSize = 0 * 1024; // bytes, 0 disables chunking
    let socketOpen = false;
    let socketSend = false;

    // ********************************************
    //  Generate / get UUID assigned by server
    // ********************************************
    function uuid() {
        return uuidValue;
    }

    // ********************************************
    //  Initialize socket connection
    // ********************************************
    function init() {
        if (socket !== null) {
            socket.close();
        }

        try {
            socket = new WebSocket(server + app);
            callbacks.status('Trying to connect ...');
        } catch (e) {
            socket = null;
            callbacks.status('WebSocket initialization failed: ' + e.message);
            return;
        }

        // -----------------------------------------
        //  Handle socket open
        // -----------------------------------------
        socket.onopen = function () {
            queue = [];
            socketOpen = true;
            socketSend = true;    
            callbacks.status('Connected');
        };

        // -----------------------------------------
        //  Handle socket errors
        // -----------------------------------------
        socket.onerror = function (err) {
            if (!socketSend) {
                callbacks.status('Cannot connect to specified server');
            }
            socketSend = false;
            socketOpen = false;
            queue = [];
            console.error('WebSocket error:', err);
        };

        // -----------------------------------------
        //  Handle messages from server
        // -----------------------------------------
        socket.onmessage = function (msg) {
            if (msg.data.length === 0 || msg.data.includes('pong')) {
                return;
            }

            let packet;
            try {
                packet = JSON.parse(msg.data);
            } catch (e) {
                console.error('Invalid JSON from server:', msg.data);
                return;
            }

            switch (packet.opcode) {
                case 'next':
                {
                    // Server is ready for next message
                    queue.shift();
                    if (queue.length > 0) {
                        try {
                            socket.send(queue[0]);
                        } catch (e) {
                            console.error('Send failed:', e);
                        }
                    } else {
                        queue = [];
                    }
                    break;
                }

                case 'ready':
                {
                    socketOpen = true;
                    socketSend = true;
                    uuidValue = packet.uuid;
                    callbacks.ready(packet);
                    break;
                }

                case 'close':
                {
                    socketOpen = false;
                    socketSend = false;
                    callbacks.status('Server closed connection');
                    break;
                }

                default:
                {
                    callbacks.readmessage(packet);
                    break;
                }
            }
        };

        // -----------------------------------------
        //  Handle socket close + reconnect
        // -----------------------------------------
        socket.onclose = function () {
            queue = [];
            socketOpen = false;
            socketSend = false;
            callbacks.close();
            uuidValue = null;
        };
    }

    // ********************************************
    //  Queue messages to be sent
    // ********************************************
    function sendMsg(msgObj) {
        if (!socketSend || !socketOpen) {
            return;
        }

        const msg = JSON.stringify(msgObj);
        queue.push(msg);

        if (queue.length === 1 && socketOpen) {
            try {
                socket.send(queue[0]);
            } catch (e) {
                console.error('Failed to send message:', e);
                socketSend = false;
            }
        }
    }

    // ********************************************
    //  Promise-based async send
    // ********************************************
    async function sendAsync(msgObj) {
        return new Promise((resolve, reject) => {
            if (!socketOpen) {
                reject('Socket not open');
                return;
            }
            try {
                sendMsg(msgObj);
                resolve();
            } catch (e) {
                reject(e);
            }
        });
    }

    // ********************************************
    // Callbacks (Default) all lower case
    // ********************************************
    let callbacks = {
        status: p => p,
        ready: p => p,
        readmessage: p => p,
        close: () => ''
    };

    // ********************************************
    // Setter
    // ********************************************
    function setCallback(type, func) {
        type = type.toLowerCase();
        if (callbacks[type]) {
            callbacks[type] = func;
        }
    }

    // ********************************************
    //  Convenience message types
    // ********************************************
    function broadcast(msg) {
        sendMsg({opcode: 'broadcast', message: msg});
    }

    function feedback(msg, toUUID) {
        sendMsg({opcode: 'feedback', message: msg, uuid: toUUID, from: uuidValue});
    }

    function echo(msg) {
        sendMsg({opcode: 'echo', message: msg});
    }

    function quit() {
        if (socket) {
            socket.close();
        }
        socketOpen = false;
        socketSend = false;
    }

    function isOpen() {
        return socketOpen;
    }

    // ********************************************
    //  Public API
    // ********************************************
    return {
        init,
        sendMsg,
        sendAsync,
        uuid,
        quit,
        isOpen,
        setCallback,
        callbacks,
        broadcast,
        feedback,
        echo
    };
}