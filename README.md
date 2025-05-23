# Socklet

Welcome to Socklet! A framework-agnostic PHP WebSocket and HTTP server library that provides attribute-based routing and powerful namespaces and rooms functionality.

## Features

- WebSocket and HTTP combined server
- Attribute-based routing
- Namespaces and rooms support
- Middleware support for authentication and request processing
- Zero dependencies - built with PHP core functionality only
- Easy-to-use event-based architecture

## Installation

```bash
composer require xentixar/socklet
```

## Quick Start

```php
use Xentixar\Socklet\Core\Server;

// Initialize server
$server = new Server("0.0.0.0", 8000);

// Register your controller
$server->registerController(new MyController());

// Start the server
$server->run();
```

See the `examples/example.php` file for a complete example.

## Documentation

1. [Getting Started](docs/getting-started.md)
   - Installation
   - Basic Usage
   - Quick Example

2. [Core Concepts](docs/core-concepts.md)
   - Server Setup
   - WebSocket Events
   - HTTP Routes
   - Namespaces & Rooms
   - Middleware

3. [API Reference](docs/api-reference.md)
   - Server Class
   - WebSocket Handler
   - HTTP Handler
   - Controllers
   - Attributes

4. [Examples](docs/examples.md)
   - Basic Chat Application
   - Room Management
   - HTTP API Integration

5. [Advanced Topics](docs/advanced-topics.md)
   - Custom Middleware
   - Error Handling
   - Best Practices

## Requirements

- PHP >= 8.0

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Links

- [GitHub Repository](https://github.com/xentixar/socklet)
- [Issue Tracker](https://github.com/xentixar/socklet/issues)
- [Examples](examples)
