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
- Secure token-based broadcast authentication
- External broadcasting from any PHP script
- Environment-based configuration for flexibility
- Automatic client connection management
- WebSocket protocol features including ping/pong for connection health monitoring
- Comprehensive CORS support with configurable allowed origins, methods, and headers
- Secure origin validation for WebSocket connections and HTTP requests
- PSR-3 compliant logging system with flexible configuration options
- Exception handling with contextual logging and stack traces
- JSON data handling and serialization
- Cross-platform compatibility
- PHP client implementation for connecting to Sockeon WebSocket servers

## Documentation

For complete documentation, examples, and API reference, please visit:

[https://sockeon.github.io](https://sockeon.github.io)

## Requirements

- PHP >= 8.0

## Configuration

Sockeon can be configured using environment variables. Create a `.env` file in your project root with the following variables:

```env
# Server Configuration
SOCKEON_SERVER_HOST=0.0.0.0   # Server bind address
SOCKEON_SERVER_PORT=6001      # Server port

# Security Configuration
SOCKEON_BROADCAST_SALT=your-custom-salt-value    # Custom salt for broadcast authentication
SOCKEON_TOKEN_EXPIRATION=30                      # Token expiration time in seconds
```

These settings allow you to customize connection parameters and security features without modifying the code.

> **Security Note:** Always change the default broadcast salt in production environments.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Links

- [GitHub Repository](https://github.com/sockeon/sockeon)
- [Issue Tracker](https://github.com/sockeon/sockeon/issues)
