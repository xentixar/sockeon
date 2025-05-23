# Getting Started with Socklet

## Installation

You can install Socklet using Composer:

```bash
composer require xentixar/socklet
```

## Basic Usage

### 1. Create a Server

```php
use Xentixar\Socklet\Core\Server;

// Initialize server on localhost:8000
$server = new Server("0.0.0.0", 8000);

// Start the server
$server->run();
```

### 2. Create a Controller

```php
use Xentixar\Socklet\Core\Contracts\SocketController;
use Xentixar\Socklet\WebSocket\Attributes\SocketOn;
use Xentixar\Socklet\Http\Attributes\HttpRoute;

class ChatController extends SocketController
{
    #[SocketOn('message.send')]
    public function onMessage(int $clientId, array $data)
    {
        // Handle incoming message
        $this->broadcast('message.receive', [
            'from' => $clientId,
            'message' => $data['message']
        ]);
    }

    #[HttpRoute('GET', '/status')]
    public function getStatus($request)
    {
        return [
            'status' => 'online',
            'time' => date('Y-m-d H:i:s')
        ];
    }
}
```

### 3. Register the Controller

```php
$server->registerController(new ChatController());
```

### 4. Add Middleware (Optional)

```php
// Add WebSocket middleware
$server->addWebSocketMiddleware(function ($clientId, $event, $data, $next) {
    echo "WebSocket Event: $event from client $clientId\n";
    return $next();
});

// Add HTTP middleware
$server->addHttpMiddleware(function ($request, $next) {
    echo "HTTP Request: {$request['method']} {$request['path']}\n";
    return $next();
});
```

## WebSocket Client Example

```javascript
const socket = new WebSocket('ws://localhost:8000');

socket.onopen = () => {
    console.log('Connected to server');
    
    // Send a message
    socket.send(JSON.stringify({
        event: 'message.send',
        data: {
            message: 'Hello, World!'
        }
    }));
};

socket.onmessage = (event) => {
    const data = JSON.parse(event.data);
    console.log('Received:', data);
};
```

## Using Rooms

```php
#[SocketOn('room.join')]
public function onJoinRoom(int $clientId, array $data)
{
    $room = $data['room'] ?? null;
    if ($room) {
        $this->joinRoom($clientId, $room);
        $this->emit($clientId, 'room.joined', [
            'room' => $room
        ]);
    }
}

#[SocketOn('message.room')]
public function onRoomMessage(int $clientId, array $data)
{
    $room = $data['room'] ?? null;
    if ($room) {
        $this->broadcast('message.receive', [
            'from' => $clientId,
            'message' => $data['message']
        ], '/', $room);
    }
}
```

## SSL/TLS Support

```php
use Xentixar\Socklet\Core\SSLContext;

// Create SSL context
$sslContext = new SSLContext(
    '/path/to/certificate.crt',
    '/path/to/private.key'
);

// Initialize secure server
$server = new Server("0.0.0.0", 8443, $sslContext);
```

For more detailed information, check out the other documentation sections:
- [Core Concepts](./core-concepts.md)
- [API Reference](./api-reference.md)
- [Examples](./examples.md)
- [Advanced Topics](./advanced-topics.md)
