
function socketWebClient(server, app) {
    'use strict';

    let queue = [];
    let uuidValue = null;
    let socket = null;
    const chunkSize = 0 * 1024; // bytes, 0 disables chunking
    let socketOpen = false;
    let socketSend = false;
    let reconnectDelay = 1000; // start with 1s backoff

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
            callbackStatus('Trying to connect ...');
        } catch (e) {
            socket = null;
            callbackStatus('WebSocket initialization failed: ' + e.message);
            return;
        }

        // -----------------------------------------
        //  Handle socket open
        // -----------------------------------------
        socket.onopen = function () {
            queue = [];
            socketOpen = true;
            socketSend = true;
            reconnectDelay = 1000; // reset backoff
            callbackStatus('Connected');
        };

        // -----------------------------------------
        //  Handle socket errors
        // -----------------------------------------
        socket.onerror = function (err) {
            if (!socketSend) {
                callbackStatus('Cannot connect to specified server');
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
                case 'next': {
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

                case 'ready': {
                    socketOpen = true;
                    socketSend = true;
                    uuidValue = packet.uuid;
                    callbackReady(packet);
                    break;
                }

                case 'close': {
                    socketOpen = false;
                    socketSend = false;
                    callbackStatus('Server closed connection');
                    break;
                }

                default: {
                    callbackReadMessage(packet);
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
            callbackClose();
            attemptReconnect();
        };
    }

    // ********************************************
    //  Reconnection logic with exponential backoff
    // ********************************************
    function attemptReconnect() {
        callbackStatus(`Reconnecting in ${reconnectDelay / 1000}s...`);
        setTimeout(() => {
            reconnectDelay = Math.min(reconnectDelay * 2, 30000); // cap at 30s
            init();
        }, reconnectDelay);
    }

    // ********************************************
    //  Queue messages to be sent
    // ********************************************
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

            queue.push('bufferON');
            const nChunks = Math.floor(msg.length / chunkSize);

            for (let i = 0, j = 0; i < nChunks; i++, j += chunkSize) {
                queue.push(msg.slice(j, j + chunkSize));
            }

            if (msg.length % chunkSize > 0) {
                queue.push(msg.slice(nChunks * chunkSize));
            }

            queue.push('bufferOFF');
        }

        if ((queue.length === 1 || sendNow) && socketOpen) {
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
    //  Default callbacks (can be replaced externally)
    // ********************************************
    let callbackStatus = function (p) { return p; };
    let callbackReady = function (p) { return p; };
    let callbackReadMessage = function (p) { return p; };
    let callbackClose = function () { return ''; };

    // ********************************************
    //  Callback setters
    // ********************************************
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

    // ********************************************
    //  Convenience message types
    // ********************************************
    function broadcast(msg) {
        sendMsg({ opcode: 'broadcast', message: msg });
    }

    function feedback(msg, toUUID) {
        sendMsg({ opcode: 'feedback', message: msg, uuid: toUUID, from: uuidValue });
    }

    function echo(msg) {
        sendMsg({ opcode: 'echo', message: msg });
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
        setCallbackStatus,
        setCallbackReady,
        setCallbackReadMessage,
        setCallbackClose,
        broadcast,
        feedback,
        echo
    };
}
