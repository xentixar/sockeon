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

use Sockeon\Sockeon\Core\Contracts\SocketController;
use Sockeon\Sockeon\WebSocket\WebSocketHandler;
use Sockeon\Sockeon\Http\HttpHandler;

class Server
{
    /**
     * Socket resource for the server
     * @var resource
     */
    protected $socket;
    
    /**
     * Active client connections
     * @var array
     */
    protected array $clients = [];
    
    /**
     * Type of connections for each client (WebSocket or HTTP)
     * @var array
     */
    protected array $clientTypes = []; // 'ws' for WebSocket, 'http' for HTTP
    
    /**
     * Custom data associated with clients
     * @var array
     */
    protected array $clientData = []; // Store client-specific data
    
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
     * Server constructor
     * 
     * @param string      $host        Host address to bind server to
     * @param int         $port        Port to bind server to
     * @param bool        $debug       Enable debug mode with verbose output
     * @param array       $corsConfig  CORS configuration options
     */
    public function __construct(
        string $host = "0.0.0.0", 
        int $port = 6001, 
        bool $debug = false,
        array $corsConfig = []
    ) {
        $this->router = new Router();
        $this->wsHandler = new WebSocketHandler($this);
        $this->httpHandler = new HttpHandler($this, $corsConfig);
        $this->namespaceManager = new NamespaceManager();
        $this->middleware = new Middleware();
        $this->isDebug = $debug;
        
        // Set up the server
        $this->socket = stream_socket_server(
            "tcp://{$host}:{$port}", 
            $errno, 
            $errstr, 
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
        );

        if (!$this->socket) {
            throw new \RuntimeException("Socket creation failed: $errstr ($errno)");
        }

        stream_set_blocking($this->socket, false);
        $this->log("Server started on tcp://{$host}:{$port}");
    }

    /**
     * Register a controller with the server
     * 
     * @param SocketController $controller  The controller instance to register
     * @return void
     */
    public function registerController($controller): void
    {
        $controller->setServer($this);
        $this->router->setServer($this);
        $this->router->register($controller);
    }

    /**
     * Get the router instance
     * 
     * @return Router  The router instance
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Get the namespace manager instance
     * 
     * @return NamespaceManager  The namespace manager instance
     */
    public function getNamespaceManager(): NamespaceManager
    {
        return $this->namespaceManager;
    }
    
    /**
     * Get the HTTP handler instance
     * 
     * @return HttpHandler  The HTTP handler instance
     */
    public function getHttpHandler(): HttpHandler
    {
        return $this->httpHandler;
    }
    
    /**
     * Get the middleware instance
     * 
     * @return Middleware  The middleware instance
     */
    public function getMiddleware(): Middleware
    {
        return $this->middleware;
    }
    
    /**
     * Add a WebSocket middleware
     * 
     * @param \Closure $middleware  The middleware function
     * @return self                 This server instance for method chaining
     */
    public function addWebSocketMiddleware(\Closure $middleware): self
    {
        $this->middleware->addWebSocketMiddleware($middleware);
        return $this;
    }
    
    /**
     * Add an HTTP middleware
     * 
     * @param \Closure $middleware  The middleware function
     * @return self                 This server instance for method chaining
     */
    public function addHttpMiddleware(\Closure $middleware): self
    {
        $this->middleware->addHttpMiddleware($middleware);
        return $this;
    }

    /**
     * Start the server and listen for connections
     * 
     * @return void
     */
    public function run(): void
    {
        $this->log("Server running...");
        
        while (true) {
            $read = $this->clients;
            $read[] = $this->socket;
            $write = $except = null;

            if (stream_select($read, $write, $except, 0, 200000)) {
                if (in_array($this->socket, $read)) {
                    $client = stream_socket_accept($this->socket);
                    stream_set_blocking($client, false);
                    $clientId = (int)$client;
                    $this->clients[$clientId] = $client;
                    // Initially unknown protocol
                    $this->clientTypes[$clientId] = 'unknown';
                    $this->namespaceManager->joinNamespace($clientId);
                    unset($read[array_search($this->socket, $read)]);
                    $this->log("Client connected: $clientId");
                }

                foreach ($read as $client) {
                    $clientId = (int)$client;
                    $data = fread($client, 8192);
                    
                    if ($data === '' || $data === false) {
                        $this->disconnectClient($clientId);
                        continue;
                    }

                    // Determine protocol if unknown
                    if ($this->clientTypes[$clientId] === 'unknown') {
                        if (str_starts_with($data, 'GET ') || str_starts_with($data, 'POST ') || 
                            str_starts_with($data, 'PUT ') || str_starts_with($data, 'DELETE ') ||
                            str_starts_with($data, 'OPTIONS ') || str_starts_with($data, 'PATCH ') ||
                            str_starts_with($data, 'HEAD ')) {
                            if (strpos($data, 'Upgrade: websocket') !== false) {
                                $this->clientTypes[$clientId] = 'ws';
                                $this->log("WebSocket client identified: $clientId");
                            } else {
                                $this->clientTypes[$clientId] = 'http';
                                $this->log("HTTP client identified: $clientId");
                            }
                        }
                    }

                    // Process based on client type
                    if ($this->clientTypes[$clientId] === 'ws') {
                        $keepAlive = $this->wsHandler->handle($clientId, $client, $data);
                        if (!$keepAlive) {
                            $this->disconnectClient($clientId);
                        }
                    } elseif ($this->clientTypes[$clientId] === 'http') {
                        $this->httpHandler->handle($clientId, $client, $data);
                        // HTTP connections are closed after handling
                        $this->disconnectClient($clientId);
                    } else {
                        // Unknown protocol, close connection
                        $this->log("Unknown protocol, disconnecting client: $clientId");
                        $this->disconnectClient($clientId);
                    }
                }
            }
        }
    }

    /**
     * Disconnect a client from the server
     * 
     * @param int $clientId  The client ID to disconnect
     * @return void
     */
    public function disconnectClient(int $clientId): void
    {
        if (isset($this->clients[$clientId])) {
            fclose($this->clients[$clientId]);
            unset($this->clients[$clientId]);
            unset($this->clientTypes[$clientId]);
            unset($this->clientData[$clientId]); // Clean up client data
            $this->namespaceManager->leaveNamespace($clientId);
            $this->log("Client disconnected: $clientId");
        }
    }

    /**
     * Set data for a specific client
     * 
     * @param int    $clientId  The client ID
     * @param string $key       The data key
     * @param mixed  $value     The data value
     * @return void
     */
    public function setClientData(int $clientId, string $key, $value): void
    {
        if (!isset($this->clientData[$clientId])) {
            $this->clientData[$clientId] = [];
        }
        
        $this->clientData[$clientId][$key] = $value;
    }
    
    /**
     * Get data for a specific client
     * 
     * @param int         $clientId  The client ID
     * @param string|null $key       Optional specific data key to retrieve
     * @return mixed                 The client data
     */
    public function getClientData(int $clientId, ?string $key = null)
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
     * @param int    $clientId  The client ID to send to
     * @param string $event     The event name
     * @param array  $data      The data to send
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
     * @param string      $event      The event name
     * @param array       $data       The data to send
     * @param string|null $namespace  Optional namespace to broadcast within
     * @param string|null $room       Optional room to broadcast to
     * @return void
     */
    public function broadcast(string $event, array $data, ?string $namespace = null, ?string $room = null): void
    {
        $frame = $this->wsHandler->prepareMessage($event, $data);
        
        if ($room !== null && $namespace !== null) {
            // Broadcast to a specific room in a namespace
            $clients = $this->namespaceManager->getClientsInRoom($room, $namespace);
        } elseif ($namespace !== null) {
            // Broadcast to an entire namespace
            $clients = $this->namespaceManager->getClientsInNamespace($namespace);
        } else {
            // Broadcast to all clients
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
     * @param int    $clientId   The client ID to add
     * @param string $room       The room name
     * @param string $namespace  The namespace
     * @return void
     */
    public function joinRoom(int $clientId, string $room, string $namespace = '/'): void
    {
        $this->namespaceManager->joinRoom($clientId, $room, $namespace);
    }
    
    /**
     * Remove a client from a room
     * 
     * @param int    $clientId   The client ID to remove
     * @param string $room       The room name
     * @param string $namespace  The namespace
     * @return void
     */
    public function leaveRoom(int $clientId, string $room, string $namespace = '/'): void
    {
        $this->namespaceManager->leaveRoom($clientId, $room, $namespace);
    }
    
    /**
     * Log a message if debug mode is enabled
     * 
     * @param string $message  The message to log
     * @return void
     */
    public function log(string $message): void
    {
        if ($this->isDebug) {
            echo "[" . date('Y-m-d H:i:s') . "] $message\n";
        }
    }
}
