<meta name="google-site-verification" content="9RThX62pakuWChXBfUw-llDMYzLJmCaxw94glD6aTUI" />

# phpWebSocketServer

Server written in PHP to handle connections via websocksets **wss:// or ws://**  
and normal sockets over **ssl://**  

As of 2020-12-07 it works also with PHP 8.0   

2021-??-?? Runtime parametrs are now read from file specified with option   
-i &lt;inifile> ; default is websock.ini in server directory

2021-08-22 Server and client can now handle very long messages (>8192B)   
no longer need to use own buffere mechanism.

# A detailed documentation is located here: 
## https://hgsweb.de/phpwebsocketDoc     <== not up to date !!


**NO DEPENDENCIES**

implemented by  
- **Heinz Schweitzer** https://github.com/napengam/phpWebSocketServer 
to work for communicating over secure websocket wss://
and accept any other socket connection by PHP processes or other 

WebSocketServer is based on the implementation in PHP by  
- **Bryan Bliewert**, nVentis@GitHub https://github.com/nVentis/PHP-WebSocketServer

The idea of *application classes' is taken from  
- **Simon Samtleben** https://github.com/bloatless/php-websocket

See also https://tools.ietf.org/html/rfc6455


# What is it good for ?

This server allows you to establish communication between web applications living in a browser  
and enables backend scripts, in my case PHP, to communicate information back to web applications that 
have called the backend script to perform some action.

Clients receive a UUID from the server upon connection. If the web application triggers  
backend scripts via AJAX it passes the UUID to the backend scripts. The script is now able to report  
back to the web client by sending the UUID along with an opcode 'feedback' and other parameters to the server.  
With the given UUID the server now knows to what web client to send the message. Loop closed !

See example in directory webClient

# Installation

- Transfer the director  `phpWebSocketServer` to the documents root of your webserver

# Configuration
## Part 1

- Step into the `server` directory and adapt the 
- `websock.ini` to your needs.  
You will find some documentation in this file.

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

**php2php**

Example how to make one php script send messages to other php script 
via websocket

**webClient**

Example of web client to connect and communicate with the server  using resource **/web**

**websocketExtern**

Conneting to external websocket servers to test cleint code



# Some Numbers


- Number of Files
- 'php' => int 24
- 'js' => int 2  

- Number of Lines of Code
- 'php' => int 1950
- 'js' => int 313  

- Size in KBytes
- 'php' => float 59
- 'js' => float 10

