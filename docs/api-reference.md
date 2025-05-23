# API Reference

## Server Class

### Constructor

```php
public function __construct(
    string $host = "0.0.0.0",
    int $port = 6001,
    ?SSLContext $sslContext = null,
    bool $debug = false
)
```

### Methods

#### Core Methods

```php
public function run(): void
public function registerController(SocketController $controller): void
public function addWebSocketMiddleware(callable $middleware): void
public function addHttpMiddleware(callable $middleware): void
```

#### Client Management

```php
public function getClientData(int $clientId, string $key): mixed
public function setClientData(int $clientId, string $key, mixed $value): void
public function removeClient(int $clientId): void
```

#### Broadcasting

```php
public function broadcast(string $event, array $data, string $namespace = '/', ?string $room = null): void
public function emit(int $clientId, string $event, array $data): void
```

## WebSocket Handler

### Methods

```php
public function handle(int $clientId, $client, string $data): bool
protected function performHandshake(int $clientId, $client, string $data): bool
protected function decodeWebSocketFrame(string $data): array
protected function encodeWebSocketFrame(string $payload, int $opcode = 1): string
```

## HTTP Handler

### Methods

```php
public function handle(int $clientId, $client, string $data): void
protected function parseHttpRequest(string $data): array
protected function processRequest(array $request): string
```

## SocketController

### Base Methods

```php
public function emit(int $clientId, string $event, array $data): void
public function broadcast(string $event, array $data, string $namespace = '/', ?string $room = null): void
public function joinRoom(int $clientId, string $room): void
public function leaveRoom(int $clientId, string $room): void
```

## Attributes

### SocketOn

```php
#[Attribute]
class SocketOn
{
    public function __construct(string $event)
}
```

### HttpRoute

```php
#[Attribute]
class HttpRoute
{
    public function __construct(string $method, string $path)
}
```

## SSLContext

### Constructor

```php
public function __construct(
    string $certificate,
    string $privateKey,
    ?string $certPassword = null
)
```

## Router

### Methods

```php
public function addRoute(string $method, string $path, callable $handler): void
public function addSocketEvent(string $event, callable $handler): void
public function dispatch(int $clientId, string $event, array $data): mixed
public function dispatchHttp(array $request): mixed
```

## NamespaceManager

### Methods

```php
public function joinNamespace(int $clientId, string $namespace): void
public function leaveNamespace(int $clientId, string $namespace): void
public function joinRoom(int $clientId, string $room, string $namespace = '/'): void
public function leaveRoom(int $clientId, string $room, string $namespace = '/'): void
public function getClientsInRoom(string $room, string $namespace = '/'): array
```

## Middleware

### Interface

```php
interface MiddlewareInterface
{
    public function process($request, callable $next);
}
```

### Usage Example

```php
$server->addWebSocketMiddleware(function ($clientId, $event, $data, $next) {
    // Pre-processing
    $result = $next();
    // Post-processing
    return $result;
});
```

For practical examples and use cases, see:
- [Examples](./examples.md)
- [Getting Started](./getting-started.md)
