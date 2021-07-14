# Usage

To get an idea how things work and relate to each other  
on the server side,have a look into  `runSocketServer.php`.

Clients to test the server are located in 

- `../webClient`
- `../phpClient`

# Files

## RFC6455.php

A php trait used in class `webSocketServer.php`  
Implements methods 

Method|What
------|----
decode| decode messages coming from a websocket
encode| encode message to be send to a websocket
handshake|handle handshake with connecting clients


## logToFile

Class to handle all loging and log rotation.

## resource.php

Base class that implements empty methodes required in order   
to register a resource with the server.

Method|What
------|----
onData    | data received from client
onClose   | socket has been closed AND deleted
onError   | any connection-releated error
onOther   | any connection-releated notification


If any of the above methods are missing the application will be rejected by the server.   

Method|What
------|----
getPacket | decode JSON packet and check for JSON errors

This class also provides a method enabling the server to register itself with  
the application, thus giving access to information within the server like sockets  
and client structures.

Method|What
------|----
registerServer    | // as said




## resourceDefault.php

This class extends `resource.php`  
Class that will serve requests for resource **/** 

`[ws,wss,tcp,ssl]://socket.server.php:port/`

## resourcePHP.php
This class extends `resource.php`  
Class that will serve requests for resource **PHP** 

`[tcp,ssl]://socket.server.php:port/php`


## resourceWeb.php
This class extends `resource.php`  
Class that will serve requests for resource  **web**

`[ws,wss]://socket.server.php:port/web`

## webSocketServer.php

Class to implement the server.  
Consumes trait `RFC6465.php`

The server handles connection request from clients, performes a handshake with clients.   

To keep track of all the clients an associated sockets there are two array where we store  
this information.  
`$this->Sockets=[]`  
`$this->Clients=[]`

Lets say a client is accepted on `$clientSocket`  then   

With `SocketID=intval($clientSocket)` we generate an index into  
`$this->Sockets[$SocketID]=$clientSocket;`   
`$this->Clients[$SocketID]=(objcet)[/*attributes*/]`      
under witch we store and retreive the needed information.


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

First create an instance of the server


Next registers the application  
- `resourceDefault.php` 
- `resourceWeb.php` 
-`resourcePHP.php` 

with the server.  

Next starts the server.

On a shell on Linux  just start it, with logging to console, like:

> php runSocketserver.php co=1

you shoud then see an out put like the one below on system using SSL


> php runSocketServer.php  co=1   
> Wed, 14 Jul 2021 10:01:21 +0200; Server initialized on Linux  xxx.yyy.net:8096 ssl://  
> Wed, 14 Jul 2021 10:01:21 +0200; Starting server...  
> Wed, 14 Jul 2021 10:01:21 +0200; Registered resource : /  
> Wed, 14 Jul 2021 10:01:21 +0200; Registered resource : /web  

on a system not using SSL you should see a similar output like the one below

> php runSocketServer.php co=1  
> Wed, 14 Jul 2021 09:04:35 +0200; Server initialized on WINNT  localhost:8091   
> Wed, 14 Jul 2021 09:04:35 +0200; Starting server...  
> Wed, 14 Jul 2021 09:04:35 +0200; Registered resource : /  
> Wed, 14 Jul 2021 09:04:35 +0200; Registered resource : /web  
> Wed, 14 Jul 2021 09:04:35 +0200; Registered resource : /php  
>  


## makeCertKey

Linux shell script to create file containing sertificate and key

## websocketserver.service

Unit file to create service/deamon for websocketserver on Linux 

