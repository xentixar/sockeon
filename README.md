# Sockeon

Welcome to Sockeon! A framework-agnostic PHP WebSocket and HTTP server library that provides attribute-based routing and powerful namespaces and rooms functionality.

## Features

- WebSocket and HTTP combined server
- Attribute-based routing for both WebSocket events and HTTP endpoints
- Advanced HTTP request and response handling
- Path parameters and query parameter support
- RESTful API support with content negotiation
- Namespaces and rooms support for WebSocket communication
- Middleware support for authentication and request processing
- Zero dependencies - built with PHP core functionality only
- Easy-to-use event-based architecture
- Real-time bidirectional communication
- Room-based broadcasting for efficient message distribution
- Automatic client connection management
- JSON data handling and serialization
- Cross-platform compatibility

## Installation

```bash
composer require sockeon/sockeon
```

## Quick Start

```php
use Sockeon\Sockeon\Core\Server;
use Sockeon\Sockeon\Core\Contracts\SocketController;
use Sockeon\Sockeon\WebSocket\Attributes\SocketOn;
use Sockeon\Sockeon\Http\Attributes\HttpRoute;
use Sockeon\Sockeon\Http\Request;
use Sockeon\Sockeon\Http\Response;

class MyController extends SocketController
{
    // WebSocket event handler
    #[SocketOn('message')]
    public function handleMessage(int $clientId, array $data)
    {
        $this->broadcast('message', [
            'from' => $clientId,
            'text' => $data['text'] ?? ''
        ]);
    }
    
    // HTTP route with path parameter
    #[HttpRoute('GET', '/users/{id}')]
    public function getUser(Request $request): Response
    {
        $userId = $request->getParam('id');
        return Response::json([
            'id' => $userId,
            'name' => 'Example User'
        ]);
    }
}

// Initialize server
$server = new Server("0.0.0.0", 8000);

// Register your controller
$server->registerController(new MyController());

// Start the server
$server->run();
```

## Documentation

For complete documentation, examples, and API reference, please visit:

[https://sockeon.github.io](https://sockeon.github.io)

## Requirements

- PHP >= 8.0

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Links

- [GitHub Repository](https://github.com/sockeon/sockeon)
- [Issue Tracker](https://github.com/sockeon/sockeon/issues)