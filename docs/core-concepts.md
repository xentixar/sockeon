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

// Basic route
#[HttpRoute('GET', '/api/status')]
public function getStatus(Request $request): Response
{
    return Response::json(['status' => 'online']);
}

// Route with path parameters
#[HttpRoute('GET', '/users/{id}')]
public function getUser(Request $request): Response
{
    $userId = $request->getParam('id');
    return Response::json(['userId' => $userId]);
}

// Route with query parameters
// Access via: /search?q=term&limit=10
#[HttpRoute('GET', '/search')]
public function search(Request $request): Response
{
    $query = $request->getQuery('q', '');
    $limit = $request->getQuery('limit', 10);
    return Response::json(['results' => []]);
}
```

#### Request Object

The `Request` class encapsulates HTTP request data and provides convenient methods to access headers, query parameters, path parameters, and the request body:

```php
use Xentixar\Socklet\Http\Request;

#[HttpRoute('GET', '/users/{id}')]
public function getUser(Request $request)
{
    // Access path parameters from URL segments
    $userId = $request->getParam('id');
    
    // Access query parameters from the URL string
    $format = $request->getQuery('format', 'json');
    
    // Access headers (case-insensitive)
    $userAgent = $request->getHeader('User-Agent');
    $contentType = $request->getHeader('Content-Type');
    
    // Request type checks
    if ($request->isJson()) {
        // Handle JSON request
    }
    
    if ($request->isAjax()) {
        // Handle AJAX request
    }
    
    if ($request->isMethod('POST')) {
        // Handle specific HTTP method
    }
    
    // Get client information
    $url = $request->getUrl();
    $ip = $request->getIpAddress();
    
    // Access body data
    $body = $request->getBody();
    
    return ['userId' => $userId];
}
```

#### Response Object

The `Response` class provides a structured way to create HTTP responses with status codes, headers, and body content:

```php
use Xentixar\Socklet\Http\Response;

#[HttpRoute('GET', '/api/products')]
public function listProducts(Request $request)
{
    // Common response types
    return Response::json([
        'products' => $products,
        'count' => count($products)
    ]);
    
    // Status code responses
    return Response::ok(['message' => 'Success']);      // 200 OK
    return Response::created(['id' => 123]);            // 201 Created
    return Response::noContent();                       // 204 No Content
    return Response::badRequest('Invalid input');       // 400 Bad Request
    return Response::unauthorized('Login required');    // 401 Unauthorized
    return Response::forbidden('No access');            // 403 Forbidden
    return Response::notFound('Resource not found');    // 404 Not Found
    return Response::serverError('Server error');       // 500 Server Error
    
    // Specialized responses
    return Response::redirect('/login');                // 302 Redirect
    return Response::download($data, 'report.csv');     // File download
    
    // Custom response with fluent API
    return (new Response($html))
        ->setContentType('text/html')
        ->setHeader('X-Custom', 'Value')
        ->setStatusCode(200);
}
```

## Namespaces & Rooms

Socklet provides a powerful system of namespaces and rooms for organizing WebSocket connections.

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
use Xentixar\Socklet\Http\Request;

$server->addHttpMiddleware(function (Request $request, $next) {
    // Add request timestamp using the setData method
    $request->setData('timestamp', time());
    
    // Log the request
    error_log("Request to: " . $request->getUrl());
    
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
