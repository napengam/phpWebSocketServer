# phpWebSocketServer 

Server witten in PHP that can handle connections via websocksets and normal sockets.
The original version was implemented by Bryan Bliewert, nVentis@GitHub
https://github.com/nVentis/PHP-WebSocketServer

I have just made some minor modifications and implemented the <b>secure version</b>.
https://github.com/napengam/phpWebSocketServer

# NOTE


# Still under development !!


Use at own risc. 

## coreAPP.php

Base clase that implements all the methodes required in order
to register an application with the server.

## appPHP.php

Application class that will server requests for resource 

[ws,wss,tcp,ssl]://<socketserver>:<port>/php
        

## appWeb.php

Application class that will server requests for resource 

[ws,wss,tcp,ssl]://<socketserver>:<port>/web



## coreFunc.php

A php trait used in class webSocketServer.php 
Implements methods for encode, decode etc... 


## webSocketServer.php

Class  implements the server using <code>stream_socket_server</code>.

## runSocketServer.php

registers the application appWeb.php and appPHP.php with the server
the starts the server.

On a shell on Linux  just start it like:

> php runSocketserver.php

you shoud then see an out put like the one below on system using SSL

[Tue, 20 Nov 18 11:35:21 +0100] - Server initialized on Linux  ssl://xyzabc.worldserver.net:8083

[Tue, 20 Nov 18 11:35:21 +0100] - Starting server...


on a system not using SSL you should see a similar output like the one below
> php runSocketserver.php

[Tue, 20 Nov 18 11:40:29 +0100] - Server initialized on WINNT  tcp://127.0.0.1:8083

[Tue, 20 Nov 18 11:40:29 +0100] - Starting server...