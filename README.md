# phpWebSocketServer

Server witten in PHP that can handle connections via websocksets and normal sockets,
over ssl:// ,tcp://  wss:// or ws://


# Directories

## Server

Implemention of the server and php script to start server

## phpClient

PHP class to establish connection and communication to a Server through <code>socket</code> and a
php script to test connection.

## webClient

javascript to establish connection and communication to a Server through <code>websocket</code> and a
php script to test connection via java script.

# Logic

This server, listening on a socket, offers you a way to have web clients and php backend scripts 
communicate with each other. Messages are exchanged using the JSON format. In this implementation
a key value pair as  {'opcode':value ,.....} is always inlcuded to trigger desired operations 
in the server or on the web client side.

## Web client

For javascript web clients sending and receiving messages is implemented by using
the websocket interface. The web client, when connecting to the server, receives a 'ready' message, when
connection and handshake are completed. The web client then is sending a UUID to the server in order to be 
registered in the server. 

## Server action

The server, when receiving a message form any client, reacts to the embedded 'opcode' if this is one of

<ul>
<li> uuid 
<li> feedback
<li> quit
</ul>

If none of the above opcode is seen, the message is broadcasted to all other registered web clients.
The receiving clients in turn will look at the embeded opcode and react as implemented.

## PHP scripts

Enabling a php script to connect to the same server offers some more posibilities for communication.

The web client triggers php scripts via AJAX and passes the same UUID to the php script, the script is
now able to report back to the web client by sending the UUID along with an opcode 'feedback'  and other parameters to the server.
With the given UUID the server noew knows to what client-web-socket to send the message. Loop closed !     

  