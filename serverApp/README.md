
## coreAPP.php

Base clase that implements all the methodes required in order
to register an application with the server.
## coreFunc.php

A php trait used in class webSocketServer.php 
Implements methods for encode, decode etc... 

## appPHP.php

Application class that will server requests for resource 

[ws,wss,tcp,ssl]://<socketserver>:<port>/php
        
## appWeb.php

Application class that will server requests for resource 

[ws,wss,tcp,ssl]://<socketserver>:<port>/web


## webSocketServer.php

Class  implements the server using <code>stream_socket_server</code>.

## runSocketServer.php

Example:

Registers the application <code>appWeb.php</code> and <code>appPHP.php</code> with the server
then starts the server.

On a shell on Linux  just start it like:

> php runSocketserver.php

you shoud then see an out put like the one below on system using SSL

[Thu, 03 Oct 19 16:03:36 +0200] - Server initialized on Linux  ssl://xyz.server.net:8083 using SSL
[Thu, 03 Oct 19 16:03:36 +0200] - Starting server...
[Thu, 03 Oct 19 16:03:36 +0200] - Application : /web
[Thu, 03 Oct 19 16:03:36 +0200] - Application : /php


on a system not using SSL you should see a similar output like the one below

> php runSocketserver.php

[Thu, 03 Oct 19 16:00:28 +0200] - Server initialized on WINNT  127.0.0.1:8083
[Thu, 03 Oct 19 16:00:28 +0200] - Starting server...
[Thu, 03 Oct 19 16:00:28 +0200] - Application : /web
[Thu, 03 Oct 19 16:00:28 +0200] - Application : /php