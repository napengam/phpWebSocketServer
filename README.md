<meta name="google-site-verification" content="9RThX62pakuWChXBfUw-llDMYzLJmCaxw94glD6aTUI" />

# phpWebSocketServer  

## NO DEPENDENCIES ##  
Server written in PHP to handle connections via websockets **wss:// or ws://**  
and normal sockets over **ssl://** or plain **tcp://**.

- **2026-08-17** Added non-blocking TLS negotiation, HMAC-verified UUID generation, origin whitelisting, connection/IP limits, and RFC_6455 trait integration.
- **2025-07-13** Refactored codebase for optimized buffer handling and signal management.
- **2021-09-30** Fixed ping/pong cycle to clients. Set `$pingInterval` to start pinging clients automatically.
- **2021-09-28** Echo back to client implemented.
- **2021-09-25** PHP script IPC: internal scripts can now talk to other scripts via WebSocket.
- **2021-08-22** Server and client can now handle very long messages (>8192B) without custom client-side buffering.
- **2020-12-07** Fully tested and compatible with PHP 8.0+.

# A detailed documentation is located here: 
## https://hgsweb.de/phpwebsocketDoc     

**NO DEPENDENCIES**

Implemented by:
- **Heinz Schweitzer** https://github.com/napengam/phpWebSocketServer  
  *(communicating over secure websocket wss:// and accepting socket connections by PHP processes or other applications)*

WebSocketServer is based on the implementation in PHP by:
- **Bryan Bliewert**, nVentis@GitHub https://github.com/nVentis/PHP-WebSocketServer

The concept of application classes is derived from:
- **Simon Samtleben** https://github.com/bloatless/php-websocket

See also https://tools.ietf.org/html/rfc6455

---

# Key Features & Architecture

- **RFC 6455 Protocol Trait**: Clean separation of protocol encoding/decoding frames via `use RFC_6455;`.
- **Flexible SSL/TLS Termination**:
  - **Direct TLS Mode**: Direct SSL certificate loading (`$certFile`, `$pkFile`) via PHP's stream crypto layer.
  - **Pass-Through Mode**: Operates over plain TCP when placed behind reverse proxies like Nginx or HAProxy.
- **Non-Blocking Crypto Negotiation**: Stalled or malicious TLS handshakes are dropped automatically via a 3-second grace-period timeout.
- **Cryptographic Client UUIDs**: UUIDs generated per client connection are signed using HMAC (`sha256`) and a random server secret.
- **Security & Connection Limits**:
  - Configurable `$allowedOrigins` to block unauthorized cross-site WebSocket requests.
  - Limit total connections with `$maxClients`.
  - Limit connections per client IP via `$maxPerIP`.
- **Auto Ping/Pong Health Checks**: Automatic eviction of dead or unresponsive connections during keep-alive sweeps.

---

# What is it good for?

This server allows you to establish communication between web applications living in a browser  
and enables backend scripts (e.g., PHP CLI or web requests) to communicate information back to web applications that 
have called the backend script to perform an action.

Clients receive an HMAC-signed UUID from the server upon connection. When the web application triggers  
backend scripts via AJAX, it passes the UUID to the backend scripts. The script is now able to report  
back to the web client by sending the UUID along with an opcode `'feedback'` and other parameters to the server.  
With the given UUID, the server sends the message directly to the matching web client. Loop closed!

See example in directory `webClient`.

---

# Quick Usage Example

### Server Setup (`server.php`)

```php
<?php

require_once __DIR__ . '/ClassLoader.php';
ClassLoader::load(['src']);

class AppHandler {
    private webSocketServer  $ server;

    public function registerServerMethods( $ server): void {
        $this->server =  $ server;
    }

    public function onOpen( $ socketId): void {
         $ this->server->echo( $ socketId, ['status' => 'connected']);
    }

    public function onData($socketId, $message): void {
         $ this->server->broadCast( $ socketId,  $ message);
    }

    public function onClose( $ socketId): void {}
    public function onError($socketId, $message): void {}
}

$logger = new class {
    public function log(string $msg): void {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    }
};

$server = new webSocketServer('0.0.0.0:8080', $logger);
$server->pingInterval = 30;
$server->maxClients = 500;
$server->maxPerIP = 5;
$server->allowedOrigins = ['https://example.com'];

$server->registerResource('/chat', new AppHandler());
$server->Start();