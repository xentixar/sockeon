# Core Concepts

This document covers the core concepts and components of the Socklet library.

## Server

The `Server` class is the main entry point of your application. It handles both WebSocket and HTTP connections on the same port.

### Key Components

- WebSocket Handler: Manages WebSocket connections and message framing
- HTTP Handler: Processes HTTP requests and responses
- Router: Routes incoming requests to appropriate controller methods
- Namespace Manager: Manages WebSocket namespaces and rooms
- Middleware: Processes requests before they reach controllers

```php
use Xentixar\Socklet\Core\Server;

$server = new Server(
    host: "0.0.0.0",      // Listen on all interfaces
    port: 8000,           // Port number
    sslContext: null,     // Optional SSL context
    debug: false          // Enable/disable debug mode
);
```

## Controllers

Controllers handle the business logic of your application. They extend `SocketController` and use attributes to define event handlers and routes.

### WebSocket Events

Use the `#[SocketOn]` attribute to handle WebSocket events:

```php
use Xentixar\Socklet\WebSocket\Attributes\SocketOn;

#[SocketOn('message.send')]
public function onMessage(int $clientId, array $data)
{
    // Handle the message
}
```

### HTTP Routes

Use the `#[HttpRoute]` attribute to handle HTTP requests:

```php
use Xentixar\Socklet\Http\Attributes\HttpRoute;

#[HttpRoute('GET', '/api/users')]
public function getUsers($request)
{
    return ['users' => []];
}
```

## Namespaces & Rooms

Socklet provides Socket.io-like namespaces and rooms for organizing WebSocket connections.

### Namespaces

Namespaces provide a way to separate concerns in your application:

```php
// In your controller
$this->broadcast('event', $data, '/admin');  // Broadcast to admin namespace
```

### Rooms

Rooms allow grouping clients for targeted messaging:

```php
// Join a room
$this->joinRoom($clientId, 'room1');

// Leave a room
$this->leaveRoom($clientId, 'room1');

// Broadcast to a room
$this->broadcast('event', $data, '/', 'room1');
```

## Middleware

Middleware allows you to process requests before they reach your controllers.

### WebSocket Middleware

```php
$server->addWebSocketMiddleware(function ($clientId, $event, $data, $next) {
    // Authenticate client
    if (!authenticate($clientId)) {
        return false;
    }
    
    // Continue to next middleware
    return $next();
});
```

### HTTP Middleware

```php
$server->addHttpMiddleware(function ($request, $next) {
    // Add request timestamp
    $request['timestamp'] = time();
    
    // Continue to next middleware
    return $next();
});
```

## Message Flow

1. Client connects to server
2. Server performs WebSocket handshake (for WS connections)
3. Messages go through middleware chain
4. Router dispatches to appropriate controller method
5. Controller processes the request
6. Response sent back to client

## Event System

### Client to Server

```javascript
socket.send(JSON.stringify({
    event: 'message.send',
    data: {
        message: 'Hello'
    }
}));
```

### Server to Client

```php
// Send to specific client
$this->emit($clientId, 'message.receive', $data);

// Broadcast to all clients
$this->broadcast('message.receive', $data);

// Broadcast to room
$this->broadcast('message.receive', $data, '/', 'room1');
```

## Error Handling

```php
try {
    $server->run();
} catch (\Exception $e) {
    error_log("Server error: " . $e->getMessage());
}
```

For more detailed information about specific components, check out:
- [API Reference](./api-reference.md)
- [Advanced Topics](./advanced-topics.md)
