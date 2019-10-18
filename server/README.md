# Directories


## coreAPP.php

Base class that implements empty methodes required in order   
to register an application with the server.

Method|What
------|----
onData    | // ...data received from client
onClose   | // ...socket has been closed AND deleted
onError   | // ...any connection-releated error
onOther   | // ...any connection-releated notification
onOpening | // ...being accepted and added to the client list

If any of these methods are missing the application will be rejected by the serve.   

This class also provides a method enabling the server to register itself with  
the application, thus giving access to information within the server like sockets  
and client structures.

Method|What
------|----
registerServer    | // as said


## coreFunc.php

A php trait used in class `webSocketServer.php`  
Implements methods 

Method|What
------|----
decode| decode messages coming from a websocket
encode| encode message to be send to a websocket
handshake|handle handshake with connecting clients
addClient| add a client object to array of clients
Log| log messages to console or file

## appPHP.php

Application class that will server requests for resource  

`[ws,wss,tcp,ssl]://socket.server.php:port/php`

This class extends `coreApp.php`

## appWeb.php

Application class that will server requests for resource 


`[ws,wss,tcp,ssl]://socket.server.php:port/web`

This class extends `coreApp.php`

## webSocketServer.php

Class to implement the server.  
Consumes trait `coreFunc.php`

## runSocketServer.php

Example:

Registers the application  `appWeb.php` and `appPHP.php` with the server
then starts the server.

On a shell on Linux  just start it like:

> php runSocketserver.php

you shoud then see an out put like the one below on system using SSL


> [Thu, 03 Oct 19 16:03:36 +0200] - Server initialized on Linux  ssl://xyz.server.net:8083 using SSL  
> [Thu, 03 Oct 19 16:03:36 +0200] - Starting server...  
> [Thu, 03 Oct 19 16:03:36 +0200] - Application : /web  
> [Thu, 03 Oct 19 16:03:36 +0200] - Application : /php  

on a system not using SSL you should see a similar output like the one below

> php runSocketserver.php

> [Thu, 03 Oct 19 16:00:28 +0200] - Server initialized on WINNT  127.0.0.1:8083  
> [Thu, 03 Oct 19 16:00:28 +0200] - Starting server...  
> [Thu, 03 Oct 19 16:00:28 +0200] - Application : /web  
> [Thu, 03 Oct 19 16:00:28 +0200] - Application : /php  

