# phpWebSocketServer 

Server witten in PHP that can handle connections via websocksets and normal sockets.
The original version was implemented by Bryan Bliewert, nVentis@GitHub
https://github.com/nVentis/PHP-WebSocketServer

I have just made some minor modifications and implemented the <b>secure version</b>.
https://github.com/napengam/phpWebSocketServer

# NOTE



# Still under development !!


Use at own risc. 

## coreFunc.php

A php trait used in class webSocketServer.php 
Implements methods for encode, decode etc... 


## webSocketServer.php

Class  implements the server using <code>stream_socket_server</code>.

## runSocketServer.php

Class extends and customizes webSocketServer.php and starts the server 
with the given parameters in adressPort.inc.php and certPath.inc.php

On a shell on Linux  just start it like:

> php runSocketserver.php

you shoud then see an out put like the one below on system using SSL

[Tue, 20 Nov 18 11:35:21 +0100] - Server initialized on Linux  ssl://xyzabc.worldserver.net:8083

[Tue, 20 Nov 18 11:35:21 +0100] - Starting server...


on a system not using SSL you should see a similar output like the one below
> php runSocketserver.php

[Tue, 20 Nov 18 11:40:29 +0100] - Server initialized on WINNT  tcp://127.0.0.1:8083

[Tue, 20 Nov 18 11:40:29 +0100] - Starting server...