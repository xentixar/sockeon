<?php

/**
 * Server class for managing WebSocket and HTTP connections
 * 
 * Main class that handles the socket server implementation, client connections,
 * and dispatches requests to appropriate handlers
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Connection;

use RuntimeException;
use Sockeon\Sockeon\Contracts\LoggerInterface;
use Sockeon\Sockeon\Controllers\SocketController;
use Sockeon\Sockeon\Core\Config;
use Sockeon\Sockeon\Core\Middleware;
use Sockeon\Sockeon\Core\NamespaceManager;
use Sockeon\Sockeon\Core\Router;
use Sockeon\Sockeon\Http\Handler as HttpHandler;
use Sockeon\Sockeon\Logging\Logger;
use Sockeon\Sockeon\Logging\LogLevel;
use Sockeon\Sockeon\WebSocket\Handler as WebSocketHandler;
use Throwable;

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
     * @param string|null $queueFile Custom queue file path
     * @throws Throwable
     */
    public function __construct(
        string $host = "0.0.0.0",
        int $port = 6001,
        bool $debug = false,
        array $corsConfig = [],
        ?LoggerInterface $logger = null,
        ?string $queueFile = null
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->router = new Router();
        $this->isDebug = $debug;

        Config::init();
        
        if ($queueFile) {
            Config::setQueueFile($queueFile);
        }

        $this->logger = $logger ?? new Logger(
            minLogLevel: $debug ? LogLevel::DEBUG : LogLevel::INFO,
            logToConsole: true,
            logToFile: false,
            logDirectory: null,
            separateLogFiles: false,
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
        $this->isDebug = $debug;
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
     * @param string $middleware The WebSocket middleware class implementing WebsocketMiddleware
     * @return self This server instance for method chaining
     */
    public function addWebSocketMiddleware(string $middleware): self
    {
        $this->middleware->addWebSocketMiddleware($middleware);
        return $this;
    }

    /**
     * Add an HTTP middleware
     *
     * @param class-string $middleware The HTTP middleware class implementing HttpMiddleware
     * @return self This server instance for method chaining
     */
    public function addHttpMiddleware(string $middleware): self
    {
        $this->middleware->addHttpMiddleware($middleware);
        return $this;
    }

    /**
     * Set the queue file path
     * 
     * @param string $queueFile The queue file path
     * @return self This server instance for method chaining
     */
    public function setQueueFile(string $queueFile): self
    {
        Config::setQueueFile($queueFile);
        return $this;
    }

    /**
     * Get the queue file path
     * 
     * @return string The queue file path
     */
    public function getQueueFile(): string
    {
        return Config::getQueueFile();
    }

    /**
     * Start the server and listen for connections
     *
     * @return void
     * @throws Throwable
     */
    public function run(): void
    {
        $this->logger->info("[Sockeon Server] Server running...");

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
            $this->logger->info("[Sockeon Server] Server started on tcp://{$this->host}:{$this->port}");
        } catch (Throwable $e) {
            $this->logger->exception($e);
            throw $e;
        }

        $lastQueueCheck = microtime(true);

        while (is_resource($this->socket)) {
            try {
                if ((microtime(true) - $lastQueueCheck) > 0.2) {
                    $this->processQueue(Config::getQueueFile());
                    $lastQueueCheck = microtime(true);
                }

                $read = array_filter($this->clients, function ($client) {
                    return is_resource($client);
                });
                
                if (is_resource($this->socket)) {
                    $read[] = $this->socket;
                } else {
                    $this->logger->error("[Sockeon Server] Invalid server socket");
                    break;
                }
                
                $write = $except = null;

                if (@stream_select($read, $write, $except, 0, 200000)) {
                    if (in_array($this->socket, $read)) {
                        try {
                            $client = @stream_socket_accept($this->socket);
                            if ($client !== false && is_resource($client)) {
                                stream_set_blocking($client, false);
                                $clientId = (int)$client;
                                $this->clients[$clientId] = $client;
                                $this->clientTypes[$clientId] = 'unknown';
                                $this->namespaceManager->joinNamespace($clientId);
                                unset($read[array_search($this->socket, $read)]);
                                $this->logger->debug("[Sockeon Connection] Client connected: $clientId");
                            }
                        } catch (Throwable $e) {
                            $this->logger->exception($e, ['context' => 'Connection acceptance']);
                        }
                    }

                    foreach ($read as $client) {
                        try {
                            if (!is_resource($client)) {
                                continue;
                            }
                            $clientId = (int)$client;
                            $data = fread($client, 8192);

                            if ($data === '' || $data === false) {
                                $this->disconnectClient($clientId);
                                continue;
                            }

                            if ($this->clientTypes[$clientId] === 'unknown') {
                                if (
                                    str_starts_with($data, 'GET ') || str_starts_with($data, 'POST ') ||
                                    str_starts_with($data, 'PUT ') || str_starts_with($data, 'DELETE ') ||
                                    str_starts_with($data, 'OPTIONS ') || str_starts_with($data, 'PATCH ') ||
                                    str_starts_with($data, 'HEAD ')
                                ) {
                                    if (str_contains($data, 'Upgrade: websocket')) {
                                        $this->clientTypes[$clientId] = 'ws';
                                        $this->logger->debug("[Sockeon Identification] WebSocket client identified: $clientId");
                                    } else {
                                        $this->clientTypes[$clientId] = 'http';
                                        $this->logger->debug("[Sockeon Identification] HTTP client identified: $clientId");
                                    }
                                }
                            }

                            if ($this->clientTypes[$clientId] === 'ws' && is_resource($client)) {
                                $keepAlive = $this->wsHandler->handle($clientId, $client, $data);
                                if (!$keepAlive) {
                                    $this->disconnectClient($clientId);
                                }
                            } elseif ($this->clientTypes[$clientId] === 'http' && is_resource($client)) {
                                $this->httpHandler->handle($clientId, $client, $data);
                                $this->disconnectClient($clientId);
                            } else {
                                $this->logger->warning("[Sockeon Identification] Unknown protocol, disconnecting client: $clientId");
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
                $this->logger->debug("[Sockeon Connection] Client disconnected: $clientId");
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

    /**
     * Process queued broadcast or emit jobs from file
     *
     * @param string $queueFile
     * @return void
     */
    protected function processQueue(string $queueFile): void
    {
        if (!file_exists($queueFile) || !is_readable($queueFile)) {
            return;
        }

        $fp = fopen($queueFile, 'r+');

        if ($fp === false) {
            $this->logger->error("[Queue] Failed to open queue file.");
            return;
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return;
        }

        $lines = [];
        while (($line = fgets($fp)) !== false) {
            $lines[] = trim($line);
        }

        ftruncate($fp, 0);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        foreach ($lines as $line) {
            if (empty($line)) continue;

            $payload = json_decode($line, true);
            if (!is_array($payload) || !isset($payload['type'])) {
                continue;
            }
            
            $type = '';
            if (is_string($payload['type'])) {
                $type = $payload['type'];
            } elseif (is_int($payload['type']) || is_float($payload['type'])) {
                $type = (string)$payload['type'];
            } else {
                $this->logger->warning("[Queue] Invalid message type format");
                continue;
            }
            
            switch ($type) {
                case 'emit':
                    if (!isset($payload['clientId'])) {
                        $this->logger->warning("[Queue] Missing clientId for emit command");
                        break;
                    }
                    
                    $clientId = 0;
                    if (is_int($payload['clientId'])) {
                        $clientId = $payload['clientId'];
                    } elseif (is_string($payload['clientId']) && is_numeric($payload['clientId'])) {
                        $clientId = (int)$payload['clientId'];
                    } else {
                        $this->logger->warning("[Queue] Invalid clientId format");
                        break;
                    }
                    
                    $event = '';
                    if (isset($payload['event'])) {
                        if (is_string($payload['event'])) {
                            $event = $payload['event'];
                        } elseif (is_int($payload['event']) || is_float($payload['event'])) {
                            $event = (string)$payload['event'];
                        }
                    }
                    
                    $data = [];
                    if (isset($payload['data']) && is_array($payload['data'])) {
                        $data = array_map(function($value, $key) {
                            return [(string)$key => $value];
                        }, $payload['data'], array_keys($payload['data']));
                        $data = empty($data) ? [] : array_merge(...$data);
                    }
                    
                    $this->send($clientId, $event, $data);
                    break;

                case 'broadcast':
                    $event = '';
                    if (isset($payload['event'])) {
                        if (is_string($payload['event'])) {
                            $event = $payload['event'];
                        } elseif (is_int($payload['event']) || is_float($payload['event'])) {
                            $event = (string)$payload['event'];
                        }
                    }
                    
                    $data = [];
                    if (isset($payload['data']) && is_array($payload['data'])) {
                        $data = array_map(function($value, $key) {
                            return [(string)$key => $value];
                        }, $payload['data'], array_keys($payload['data']));
                        $data = empty($data) ? [] : array_merge(...$data);
                    }
                    
                    $namespace = null;
                    if (isset($payload['namespace'])) {
                        if (is_string($payload['namespace'])) {
                            $namespace = $payload['namespace'];
                        } elseif (is_int($payload['namespace']) || is_float($payload['namespace'])) {
                            $namespace = (string)$payload['namespace'];
                        }
                    }
                    
                    $room = null;
                    if (isset($payload['room'])) {
                        if (is_string($payload['room'])) {
                            $room = $payload['room'];
                        } elseif (is_int($payload['room']) || is_float($payload['room'])) {
                            $room = (string)$payload['room'];
                        }
                    }
                    
                    $this->broadcast($event, $data, $namespace, $room);
                    break;

                default:
                    $this->logger->warning("[Queue] Unknown message type: {$type}");
            }
        }
    }
}
