# phpWebSocketServer

Server witten in PHP to handle connections via websocksets **wss:// or ws://** and normal sockets
over **ssl:// ,tcp://**

implemented by  
- **Heinz Schweitzer** @https://github.com/napengam/phpWebSocketServer 
to work for communicating over secure websocket wss://
and accept any other socket connection by PHP processes or other 


WebSocketServer is based on the implementation in PHP by  
- **Bryan Bliewert**, nVentis@GitHub https://github.com/nVentis/PHP-WebSocketServer

The idea of *application classes' is taken from  
- **Simon Samtleben** @https://github.com/bloatless/php-websocket

# Installation

- Transfer the director  `phpWebSocketServer` to the documents root of your webserver
- Step into the `include` directory and adapt the `adressPort.inc.php` to your needs.  
    You will find some documentation in this file.
- If your server uses  `https://` follow the instructions in `certPath.inc.php` and set the global variables in there accordingly.

To start the server see the README in directory server 


# Directories

**include**

Files included by server and clients

**Server**

Implemention of a server in php and php script to start server.  
Examples of classes to implement resources **/php** and **/web**

**phpClient**

Example of client written in PHP to connect and write to the server using resource **/php** 

**webClient**

Example of web client to connect and communicate with the server  using resource **/web**

