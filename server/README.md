# Directories


## coreAPP.php

Base class that implements empty methodes required in order   
to register an application with the server.

Method|What
------|----
onData    | data received from client
onClose   | socket has been closed AND deleted
onError   | any connection-releated error
onOther   | any connection-releated notification
onOpening | being accepted and added to the client list

If any of these methods are missing the application will be rejected by the server.   

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

The server handles connection request from clients, performes a handshake with clients.   

Upon a successful handshake, the client is registered with the server and incoming messages   
will be routed to the resource, application, the client specified in the **GET** request.  
In the given examples resources are **/web** and **/php** 

Next the server sends a message **ready** to the client, that is waiting for this  
message. A client connecting through websocket now sends its UUID which is tracked  
along other informations for this client.

Any incoming message is allways acknowledged with a **next** message to the client.  
Clients should wait for this message to arrive, before sending another message. 

Based on the pattern `bufferON` and `bufferOFF`, found as a string at the start of  
a message from a client, the server will turn *on* or *off*, buffering of very long 
messages (above 8K) for the given client.

In the given examples, messages routed to the requested resource are expected to be in JSON format  
`{'opcode': opcode, 'message':messsage .....}`

Feel free to use whatever supports your needs.


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

