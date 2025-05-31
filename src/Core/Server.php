<?php
/**
 * Server class for managing WebSocket and HTTP connections
 * 
 * Main class that handles the socket server implementation, client connections,
 * and dispatches requests to appropriate handlers
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core;

use Closure;
use RuntimeException;
use Throwable;
use Sockeon\Sockeon\Core\Contracts\SocketController;
use Sockeon\Sockeon\WebSocket\WebSocketHandler;
use Sockeon\Sockeon\Http\HttpHandler;
use Sockeon\Sockeon\Logging\Logger;
use Sockeon\Sockeon\Logging\LoggerInterface;
use Sockeon\Sockeon\Logging\LogLevel;

class Server
{
    /**
     * Host address to bind server to
     * @var string
     */
    protected string $host;

    /**
     * Port to bind server to
     * @var int
     */
    protected int $port;

    /**
     * Socket resource for the server
     * @var resource|false
     */
    protected $socket;
    
    /**
     * Active client connections
     * @var array<int, resource>
     */
    protected array $clients = [];
    
    /**
     * Type of connections for each client (WebSocket or HTTP)
     * @var array<int, string>
     */
    protected array $clientTypes = [];
    
    /**
     * Custom data associated with clients
     * @var array<int, array<string, mixed>>
     */
    protected array $clientData = [];
    
    /**
     * Router instance
     * @var Router
     */
    protected Router $router;
    
    /**
     * WebSocketHandler instance
     * @var WebSocketHandler
     */
    protected WebSocketHandler $wsHandler;
    
    /**
     * HttpHandler instance
     * @var HttpHandler
     */
    protected HttpHandler $httpHandler;
    
    /**
     * NamespaceManager instance
     * @var NamespaceManager
     */
    protected NamespaceManager $namespaceManager;
    
    /**
     * Middleware instance
     * @var Middleware
     */
    protected Middleware $middleware;
    
    /**
     * Whether debug mode is enabled
     * @var bool
     */
    protected bool $isDebug;
    
    /**
     * Logger instance
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param string $host Host address to bind server to
     * @param int $port Port to bind server to
     * @param bool $debug Enable debug mode with verbose output
     * @param array<string, mixed> $corsConfig CORS configuration options
     * @param LoggerInterface|null $logger Custom logger implementation
     * @throws Throwable
     */
    public function __construct(
        string $host = "0.0.0.0", 
        int $port = 6001, 
        bool $debug = false,
        array $corsConfig = [],
        ?LoggerInterface $logger = null
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->router = new Router();
        $this->isDebug = $debug;
        
        $this->logger = $logger ?? new Logger(
            minLogLevel: $debug ? LogLevel::DEBUG : LogLevel::INFO,
            logToConsole: true,
            logToFile: false,
            logDirectory: null,
            separateLogFiles: null,
        );

        $allowedOrigins = [];
        if (isset($corsConfig['allowed_origins']) && is_array($corsConfig['allowed_origins'])) {
            foreach ($corsConfig['allowed_origins'] as $origin) {
                if (is_string($origin)) {
                    $allowedOrigins[] = $origin;
                }
            }
        } else {
            $allowedOrigins[] = '*';
        }

        $this->wsHandler = new WebSocketHandler($this, $allowedOrigins);
        $this->httpHandler = new HttpHandler($this, $corsConfig);
        $this->namespaceManager = new NamespaceManager();
        $this->middleware = new Middleware();
    }

    /**
     * Register a controller with the server
     * 
     * @param SocketController $controller The controller instance to register
     * @return void
     */
    public function registerController(SocketController $controller): void
    {
        try {
            $controller->setServer($this);
            $this->router->setServer($this);
            $this->router->register($controller);
        } catch (Throwable $e) {
            $this->logger->exception($e);
        }
    }
    
    /**
     * Get the logger instance
     * 
     * @return LoggerInterface The logger instance
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
    
    /**
     * Set a custom logger instance
     * 
     * @param LoggerInterface $logger The logger instance
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Get the router instance
     * 
     * @return Router The router instance
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Get the namespace manager instance
     * 
     * @return NamespaceManager The namespace manager instance
     */
    public function getNamespaceManager(): NamespaceManager
    {
        return $this->namespaceManager;
    }
    
    /**
     * Get the HTTP handler instance
     * 
     * @return HttpHandler The HTTP handler instance
     */
    public function getHttpHandler(): HttpHandler
    {
        return $this->httpHandler;
    }
    
    /**
     * Get the middleware instance
     * 
     * @return Middleware The middleware instance
     */
    public function getMiddleware(): Middleware
    {
        return $this->middleware;
    }
    
    /**
     * Add a WebSocket middleware
     * 
     * @param Closure $middleware The middleware function
     * @return self This server instance for method chaining
     */
    public function addWebSocketMiddleware(Closure $middleware): self
    {
        $this->middleware->addWebSocketMiddleware($middleware);
        return $this;
    }
    
    /**
     * Add an HTTP middleware
     * 
     * @param Closure $middleware The middleware function
     * @return self This server instance for method chaining
     */
    public function addHttpMiddleware(Closure $middleware): self
    {
        $this->middleware->addHttpMiddleware($middleware);
        return $this;
    }

    /**
     * Start the server and listen for connections
     *
     * @return void
     * @throws Throwable
     */
    public function run(): void
    {
        $this->logger->info("Server running...");

        try {
            $this->socket = stream_socket_server(
                "tcp://{$this->host}:{$this->port}",
                $errno,
                $errstr,
                STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
            );

            if (!$this->socket) {
                $errorMessage = is_string($errstr) ? $errstr : 'Unknown error';
                $errorCode = is_int($errno) ? $errno : 0;
                throw new RuntimeException("Socket creation failed: " . $errorMessage . " (" . $errorCode . ")");
            }

            stream_set_blocking($this->socket, false);
            $this->logger->info("Server started on tcp://{$this->host}:{$this->port}");
        } catch (Throwable $e) {
            $this->logger->exception($e);
            throw $e;
        }
        
        while (is_resource($this->socket)) {
            try {
                $read = $this->clients;
                $read[] = $this->socket;
                $write = $except = null;

                if (stream_select($read, $write, $except, 0, 200000)) {
                    if (in_array($this->socket, $read)) {
                        try {
                            $client = stream_socket_accept($this->socket);
                            if (is_resource($client)) {
                                stream_set_blocking($client, false);
                                $clientId = (int)$client;
                                $this->clients[$clientId] = $client;
                                $this->clientTypes[$clientId] = 'unknown';
                                $this->namespaceManager->joinNamespace($clientId);
                                unset($read[array_search($this->socket, $read)]);
                                $this->logger->debug("Client connected: $clientId");
                            }
                        } catch (Throwable $e) {
                            $this->logger->exception($e, ['context' => 'Connection acceptance']);
                        }
                    }

                    foreach ($read as $client) {
                        try {
                            $clientId = (int)$client;
                            $data = fread($client, 8192);
                            
                            if ($data === '' || $data === false) {
                                $this->disconnectClient($clientId);
                                continue;
                            }

                            if ($this->clientTypes[$clientId] === 'unknown') {
                                if (str_starts_with($data, 'GET ') || str_starts_with($data, 'POST ') || 
                                    str_starts_with($data, 'PUT ') || str_starts_with($data, 'DELETE ') ||
                                    str_starts_with($data, 'OPTIONS ') || str_starts_with($data, 'PATCH ') ||
                                    str_starts_with($data, 'HEAD ')) {
                                    if (str_contains($data, 'Upgrade: websocket')) {
                                        $this->clientTypes[$clientId] = 'ws';
                                        $this->logger->debug("WebSocket client identified: $clientId");
                                    } else {
                                        $this->clientTypes[$clientId] = 'http';
                                        $this->logger->debug("HTTP client identified: $clientId");
                                    }
                                }
                            }

                            if ($this->clientTypes[$clientId] === 'ws') {
                                $keepAlive = $this->wsHandler->handle($clientId, $client, $data);
                                if (!$keepAlive) {
                                    $this->disconnectClient($clientId);
                                }
                            } elseif ($this->clientTypes[$clientId] === 'http') {
                                $this->httpHandler->handle($clientId, $client, $data);
                                $this->disconnectClient($clientId);
                            } else {
                                $this->logger->warning("Unknown protocol, disconnecting client: $clientId");
                                $this->disconnectClient($clientId);
                            }
                        } catch (Throwable $e) {
                            $clientId = (int)$client;
                            $this->logger->exception($e, ['clientId' => $clientId]);
                            
                            try {
                                $this->disconnectClient($clientId);
                            } catch (Throwable $innerEx) {
                                $this->logger->error("Failed to disconnect client after error: {$innerEx->getMessage()}");
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                $this->logger->exception($e, ['context' => 'Server main loop']);
                usleep(100000);
            }
        }
    }

    /**
     * Disconnect a client from the server
     * 
     * @param int $clientId The client ID to disconnect
     * @return void
     */
    public function disconnectClient(int $clientId): void
    {
        try {
            if (isset($this->clients[$clientId])) {
                fclose($this->clients[$clientId]);
                unset($this->clients[$clientId]);
                unset($this->clientTypes[$clientId]);
                unset($this->clientData[$clientId]);
                $this->namespaceManager->leaveNamespace($clientId);
                $this->logger->debug("Client disconnected: $clientId");
            }
        } catch (Throwable $e) {
            $this->logger->exception($e, ['context' => 'Client disconnection', 'clientId' => $clientId]);
        }
    }

    /**
     * Set data for a specific client
     * 
     * @param int $clientId The client ID
     * @param string $key The data key
     * @param mixed  $value The data value
     * @return void
     */
    public function setClientData(int $clientId, string $key, mixed $value): void
    {
        if (!isset($this->clientData[$clientId])) {
            $this->clientData[$clientId] = [];
        }
        
        $this->clientData[$clientId][$key] = $value;
    }
    
    /**
     * Get data for a specific client
     * 
     * @param int $clientId The client ID
     * @param string|null $key Optional specific data key to retrieve
     * @return mixed The client data
     */
    public function getClientData(int $clientId, ?string $key = null): mixed
    {
        if (!isset($this->clientData[$clientId])) {
            return null;
        }
        
        if ($key === null) {
            return $this->clientData[$clientId];
        }
        
        return $this->clientData[$clientId][$key] ?? null;
    }

    /**
     * Send a message to a specific client
     * 
     * @param int $clientId The client ID to send to
     * @param string $event The event name
     * @param array<string, mixed> $data The data to send
     * @return void
     */
    public function send(int $clientId, string $event, array $data): void
    {
        if (isset($this->clients[$clientId]) && $this->clientTypes[$clientId] === 'ws') {
            $frame = $this->wsHandler->prepareMessage($event, $data);
            fwrite($this->clients[$clientId], $frame);
        }
    }

    /**
     * Broadcast a message to multiple clients
     * 
     * @param string $event The event name
     * @param array<string, mixed> $data The data to send
     * @param string|null $namespace  Optional namespace to broadcast within
     * @param string|null $room Optional room to broadcast to
     * @return void
     */
    public function broadcast(string $event, array $data, ?string $namespace = null, ?string $room = null): void
    {
        $frame = $this->wsHandler->prepareMessage($event, $data);
        
        if ($room !== null && $namespace !== null) {
            $clients = $this->namespaceManager->getClientsInRoom($room, $namespace);
        } elseif ($namespace !== null) {
            $clients = $this->namespaceManager->getClientsInNamespace($namespace);
        } else {
            $clients = array_keys($this->clients);
        }
        
        foreach ($clients as $clientId) {
            if (isset($this->clients[$clientId]) && $this->clientTypes[$clientId] === 'ws') {
                fwrite($this->clients[$clientId], $frame);
            }
        }
    }
    
    /**
     * Add a client to a room
     * 
     * @param int $clientId The client ID to add
     * @param string $room The room name
     * @param string $namespace The namespace
     * @return void
     */
    public function joinRoom(int $clientId, string $room, string $namespace = '/'): void
    {
        $this->namespaceManager->joinRoom($clientId, $room, $namespace);
    }
    
    /**
     * Remove a client from a room
     * 
     * @param int $clientId The client ID to remove
     * @param string $room The room name
     * @param string $namespace The namespace
     * @return void
     */
    public function leaveRoom(int $clientId, string $room, string $namespace = '/'): void
    {
        $this->namespaceManager->leaveRoom($clientId, $room, $namespace);
    }
}
