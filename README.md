# phpWebSocketServer

Server written in PHP (7.4, 8.0) to handle connections via websocksets **wss:// or ws://**  
and normal sockets over **ssl:// ,tcp://**

**NO DEPENDENCIES**

implemented by  
- **Heinz Schweitzer** @https://github.com/napengam/phpWebSocketServer 
to work for communicating over secure websocket wss://
and accept any other socket connection by PHP processes or other 

WebSocketServer is based on the implementation in PHP by  
- **Bryan Bliewert**, nVentis@GitHub https://github.com/nVentis/PHP-WebSocketServer

The idea of *application classes' is taken from  
- **Simon Samtleben** @https://github.com/bloatless/php-websocket


# What is it good for ?

This server allows you to establish communication between web applications living in a browser  
and enables backend scripts, in my case PHP, to communicate information back to web applications that 
have called the backend script to perform some action.

In the example here, web applications identify themself with a UUID to the server. If the web application triggers  
backend scripts via AJAX it passes the UUID to the backend scripts. The script is now able to report  
back to the web client by sending the UUID along with an opcode 'feedback' and other parameters to the server.  
With the given UUID the server now knows to what web client to send the message. Loop closed !

See example in directory webClient

# Installation

- Transfer the director  `phpWebSocketServer` to the documents root of your webserver

# Configuration
## Part 1

- Step into the `include` directory and adapt the 
  - `adressPort.inc.php` to your needs.  
    You will find some documentation in this file.
  -  `logToFile.inc.pcp` set the directory where logfiles will live
- If your server uses  `https://` follow the instructions in `certPath.inc.php` and set the global variables in there accordingly.

## Part 2

To start and use the server see the [README](server/README.md) in directory server 

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

