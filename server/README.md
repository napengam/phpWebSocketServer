# phpWebSocketServer 

Server witten in PHP that can handle connections via websocksets and normal sockets.
The original version was implemented by Bryan Bliewert, nVentis@GitHub
https://github.com/nVentis/PHP-WebSocketServer

Full Credits go to him.

I have just made some minor modifications and implemented the <b>secure version</b>.
https://github.com/napengam/phpWebSocketServer

# NOTE

The behaviour of reading data using 

<code>$dataBuffer = fread($Socket, $this->bufferLength);</code>

is different between LINUX and Windows(10).

On Linux the server just reads the amount of bytes the client has send, then proceeds,
this is what I want.

On Windows the server seems to wait until  <code>$this->bufferLength</code> bytes
have been sent by the client or some timeout happens. Up to now, I have <b>no clue</b> what causes
this or how to change this, however there is a workaround build into the server and the client code.

The server detects on what OS it is running and reports this back to the clients.

If the OS is Windows the server expects from 
<ul>
<li>a PHP client to first send 32 bytes with 
the length of the message then the message itself. 
<li>a Websocket client to queue messages until the server sends the opcode <code>next</code>
to trigger the client to send the next message from its queue.  
</ul>

# Still under development !!


Use at own risc. 

## adressPort.inc.php

Holds information about certificates/key,
adress of host and port.

<b>NOTE:</b>

Specify the host adress like.
<ul>
<li> ssl://xyzabc.worldserver.net   
<li> tcp://xyzabc.worldserver.net
</ul>  


## webSocketServer.php

This implements the server using <code>stream_socket_server</code>.

## runSocketServer.php

This extends and customizes webSocketServer.php and starts the server 
with the given parameters in adressPort.inc.php

On a shell on Linux  just start it like:

> php runSocketserver.php

you shoud then see an out put like the one below on system using SSL

[Tue, 20 Nov 18 11:35:21 +0100] - Server initialized on Linux  ssl://xyzabc.worldserver.net:8083

[Tue, 20 Nov 18 11:35:21 +0100] - Starting server...


on a system not using SSL you should see a similar output like the one below
> php runSocketserver.php

[Tue, 20 Nov 18 11:40:29 +0100] - Server initialized on WINNT  tcp://127.0.0.1:8083

[Tue, 20 Nov 18 11:40:29 +0100] - Starting server...