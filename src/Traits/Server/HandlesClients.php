<?php

namespace Sockeon\Sockeon\Traits\Server;

use RuntimeException;
use Sockeon\Sockeon\Core\Config;
use Sockeon\Sockeon\Core\ConnectionPool;
use Sockeon\Sockeon\Core\AsyncTaskQueue;
use Sockeon\Sockeon\Core\PerformanceMonitor;
use Throwable;

trait HandlesClients
{
    /**
     * Connection pool for resource optimization
     * @var ConnectionPool
     */
    protected ConnectionPool $connectionPool;

    /**
     * Async task queue for heavy operations
     * @var AsyncTaskQueue
     */
    protected AsyncTaskQueue $taskQueue;

    /**
     * Performance monitoring
     * @var PerformanceMonitor
     */
    protected PerformanceMonitor $performanceMonitor;

    protected function startSocket(): void
    {
        $this->logger->info("[Sockeon Server] Starting server...");

        // Initialize scaling components
        $this->connectionPool = new ConnectionPool();
        $this->taskQueue = new AsyncTaskQueue();
        $this->performanceMonitor = new PerformanceMonitor();

        // Register task processors
        $this->registerTaskProcessors();

        // Record server start time
        $this->startTime = microtime(true);

        // Create socket with optimized options
        $context = stream_context_create([
            'socket' => [
                'so_reuseport' => 1,
                'so_keepalive' => 1,
                'backlog' => 1024,
            ],
        ]);

        $this->socket = stream_socket_server(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );

        if (!$this->socket) {
            $errorNumber = is_int($errno) ? $errno : 0;
            $errorString = is_string($errstr) ? $errstr : 'Unknown error';
            throw new RuntimeException("Failed to create socket: $errorString ($errorNumber)");
        }

        stream_set_blocking($this->socket, false);

        // Set socket options for better performance
        if (function_exists('socket_import_stream')) {
            $socketResource = socket_import_stream($this->socket);
            if ($socketResource !== false) {
                socket_set_option($socketResource, SOL_SOCKET, SO_RCVBUF, 262144); // 256KB receive buffer
                socket_set_option($socketResource, SOL_SOCKET, SO_SNDBUF, 262144); // 256KB send buffer
                socket_set_option($socketResource, SOL_TCP, TCP_NODELAY, 1); // Disable Nagle's algorithm
            }
        }
        $this->logger->info("[Sockeon Server] Listening on tcp://{$this->host}:{$this->port}");
    }

    protected function loop(): void
    {
        $lastQueueCheck = microtime(true);
        $lastBufferCleanup = microtime(true);
        $lastTaskProcessing = microtime(true);
        $lastMonitorUpdate = microtime(true);

        while (is_resource($this->socket)) {
            try {
                // Process async tasks
                if ((microtime(true) - $lastTaskProcessing) > 0.05) {
                    $this->taskQueue->processTasks();
                    $lastTaskProcessing = microtime(true);
                }

                // Update performance monitoring
                if ((microtime(true) - $lastMonitorUpdate) > 1.0) {
                    $this->updatePerformanceMetrics();
                    $lastMonitorUpdate = microtime(true);
                }

                if ((microtime(true) - $lastQueueCheck) > 0.1) {
                    $this->processQueue(Config::getQueueFile());
                    $lastQueueCheck = microtime(true);
                }

                if ((microtime(true) - $lastBufferCleanup) > 30) {
                    $this->cleanupExpiredBuffers();
                    $this->cleanupDeadConnections();
                    $this->connectionPool->cleanup();
                    $lastBufferCleanup = microtime(true);

                    // Force garbage collection periodically
                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                }

                /** @var array<resource> $readSockets */
                $readSockets = array_filter($this->clients, fn($client) => is_resource($client));
                $readSockets[] = $this->socket;
                /** @var array<resource> $read */
                $read = $readSockets;

                $write = $except = null;

                if (@stream_select($read, $write, $except, 0, 50000)) {
                    $this->acceptNewClient($read);
                    $this->handleClientData($read);
                }
            } catch (Throwable $e) {
                $this->logger->exception($e, ['context' => 'Main loop']);
                usleep(50000); // Reduced sleep for faster recovery
            }

            // Micro-sleep to prevent CPU spinning when idle, but yield to OS scheduler
            if (empty($readSockets) || count($this->clients) === 0) {
                usleep(10000); // Longer sleep when no clients
            } else {
                usleep(1000); // Short sleep when active
            }
        }
    }

    /**
     * @param array<resource> $read
     */
    protected function acceptNewClient(array &$read): void
    {
        if (in_array($this->socket, $read, true)) {
            // Accept clients with connection limit to prevent overwhelming
            $acceptedCount = 0;
            $maxAcceptPerLoop = min(5, 10000 - count($this->clients)); // Limit total connections

            while (($client = @stream_socket_accept($this->socket, 0)) !== false && $acceptedCount < $maxAcceptPerLoop) {
                if (is_resource($client)) {
                    stream_set_blocking($client, false);

                    // Generate unique client ID
                    $clientId = $this->generateClientId();
                    $resourceId = (int) $client;
                    $this->resourceToClientId[$resourceId] = $clientId;

                    // Check if we're at capacity
                    if (count($this->clients) >= 10000) {
                        @fclose($client);
                        unset($this->resourceToClientId[$resourceId]);
                        $this->logger->warning("[Sockeon Connection] Connection limit reached, rejecting client");
                        break;
                    }

                    // Try to reuse connection from pool first
                    $pooledConnection = $this->connectionPool->acquireConnection('unknown', $clientId, $client);
                    $reuseCount = isset($pooledConnection['reuse_count']) && is_int($pooledConnection['reuse_count']) ? $pooledConnection['reuse_count'] : 0;
                    if ($reuseCount > 0) {
                        // Use pooled connection data for optimization
                        $this->logger->debug("[Sockeon Connection] Reusing pooled connection for client: $clientId (reused $reuseCount times)");
                    }

                    $this->clients[$clientId] = $client;
                    $this->clientTypes[$clientId] = 'unknown';
                    $this->namespaceManager->joinNamespace($clientId);
                    $this->logger->debug("[Sockeon Connection] Client connected: $clientId");

                    // Update performance metrics
                    $this->performanceMonitor->recordRequest('connection');

                    $acceptedCount++;
                }
            }

            unset($read[array_search($this->socket, $read, true)]);
        }
    }

    /**
     * Client buffers for incomplete requests
     * @var array<string, string>
     */
    protected array $clientBuffers = [];

    /**
     * Client buffer timestamps to handle timeouts
     * @var array<string, float>
     */
    protected array $clientBufferTimestamps = [];

    /**
     * @param array<resource> $read
     */
    protected function handleClientData(array $read): void
    {
        foreach ($read as $client) {
            $clientId = $this->getClientIdFromResource($client);

            if ($clientId === null) {
                // Unknown client, disconnect
                @fclose($client);
                continue;
            }

            try {
                // Check if client is still connected before reading
                if (!is_resource($client) || feof($client)) {
                    $this->disconnectClient($clientId);
                    continue;
                }

                $data = @fread($client, 32768); // Moderate buffer size

                if ($data === '' || $data === false) {
                    $this->disconnectClient($clientId);
                    continue;
                }

                if (($this->clientTypes[$clientId] ?? 'unknown') === 'ws') {
                    $this->handleHttpWs($clientId, $client, $data);
                } else {
                    if (!isset($this->clientBuffers[$clientId])) {
                        $this->clientBuffers[$clientId] = '';
                        $this->clientBufferTimestamps[$clientId] = microtime(true);
                    }
                    $this->clientBuffers[$clientId] .= $data;

                    if ($this->isCompleteHttpRequest($this->clientBuffers[$clientId])) {
                        $this->handleHttpWs($clientId, $client, $this->clientBuffers[$clientId]);
                        unset($this->clientBuffers[$clientId], $this->clientBufferTimestamps[$clientId]);
                    } else {
                        if (microtime(true) - $this->clientBufferTimestamps[$clientId] > 15) {
                            $this->logger->warning("Client buffer timeout for client: $clientId");
                            $this->disconnectClient($clientId);
                        }
                    }
                }
            } catch (Throwable $e) {
                $this->logger->exception($e, ['clientId' => $clientId, 'context' => 'handleClientData']);
                $this->disconnectClient($clientId);
            }
        }
    }

    /**
     * Check if we have received a complete HTTP request
     *
     * @param string $data The buffered request data
     * @return bool True if the request is complete
     */
    protected function isCompleteHttpRequest(string $data): bool
    {
        if (!str_contains($data, "\r\n\r\n")) {
            return false;
        }

        $headerEndPos = strpos($data, "\r\n\r\n");
        if ($headerEndPos === false) {
            return false;
        }

        $headerSection = substr($data, 0, $headerEndPos);
        $bodySection = substr($data, $headerEndPos + 4);

        $contentLength = 0;
        $transferEncoding = '';
        $lines = explode("\r\n", $headerSection);

        foreach ($lines as $line) {
            if (stripos($line, 'Content-Length:') === 0) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $contentLength = (int) trim($parts[1]);
                }
            } elseif (stripos($line, 'Transfer-Encoding:') === 0) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $transferEncoding = strtolower(trim($parts[1]));
                }
            }
        }

        if ($transferEncoding === 'chunked') {
            return $this->isCompleteChunkedRequest($bodySection);
        }

        if ($contentLength === 0) {
            return true;
        }

        return strlen($bodySection) >= $contentLength;
    }

    /**
     * Check if a chunked request is complete
     *
     * @param string $body The request body
     * @return bool True if the chunked request is complete
     */
    protected function isCompleteChunkedRequest(string $body): bool
    {
        return str_ends_with($body, "0\r\n\r\n");
    }

    /**
     * Clean up expired client buffers
     *
     * @return void
     */
    protected function cleanupExpiredBuffers(): void
    {
        $currentTime = microtime(true);
        foreach ($this->clientBufferTimestamps as $clientId => $timestamp) {
            if ($currentTime - $timestamp > 15) {
                $this->logger->warning("Cleaning up expired buffer for client: $clientId");
                unset($this->clientBuffers[$clientId], $this->clientBufferTimestamps[$clientId]);
                $this->disconnectClient($clientId);
            }
        }
    }

    /**
     * Clean up dead connections that are no longer valid resources
     *
     * @return void
     */
    protected function cleanupDeadConnections(): void
    {
        $deadConnections = [];

        foreach ($this->clients as $clientId => $client) {
            if (!is_resource($client) || @feof($client)) {
                $deadConnections[] = $clientId;
            }
        }

        foreach ($deadConnections as $clientId) {
            $this->logger->debug("Cleaning up dead connection: $clientId");
            $this->disconnectClient($clientId);
        }

        $this->logger->info("[Sockeon Cleanup] Active connections: " . count($this->clients));
    }

    public function disconnectClient(string $clientId): void
    {
        try {
            if (isset($this->clients[$clientId])) {
                // Get resource for cleanup
                $client = $this->clients[$clientId];
                $resourceId = is_resource($client) ? (int) $client : null;

                // Only dispatch disconnect event if client was a WebSocket and still connected
                if (($this->clientTypes[$clientId] ?? null) === 'ws' && is_resource($client)) {
                    try {
                        $this->router->dispatchSpecialEvent($clientId, 'disconnect');
                    } catch (Throwable $e) {
                        // Ignore disconnect event errors
                    }
                }

                // Return connection to pool if it's still valid
                if (is_resource($client) && !@feof($client)) {
                    $this->connectionPool->releaseConnection($clientId);
                } else {
                    // Safe resource cleanup for invalid connections
                    if (is_resource($client)) {
                        @fclose($client);
                    }
                }

                // Clean up all client data
                unset($this->clients[$clientId], $this->clientTypes[$clientId], $this->clientData[$clientId]);
                if (isset($this->clientBuffers[$clientId])) {
                    unset($this->clientBuffers[$clientId], $this->clientBufferTimestamps[$clientId]);
                }

                // Clean up resource-to-clientId mapping
                if ($resourceId !== null) {
                    unset($this->resourceToClientId[$resourceId]);
                }

                $this->namespaceManager->leaveNamespace($clientId);

                $this->logger->debug("[Sockeon Connection] Client disconnected: $clientId");
            }
        } catch (Throwable $e) {
            $this->logger->exception($e, ['context' => 'Client disconnection', 'clientId' => $clientId]);
        }
    }

    public function setClientData(string $clientId, string $key, mixed $value): void
    {
        $this->clientData[$clientId][$key] = $value;
    }

    public function getClientData(string $clientId, ?string $key = null): mixed
    {
        if (!isset($this->clientData[$clientId])) {
            return null;
        }

        return $key === null ? $this->clientData[$clientId] : ($this->clientData[$clientId][$key] ?? null);
    }

    /**
     * Get the IP address of a client
     *
     * @param string $clientId The client ID
     * @return string|null The client IP address or null if not found
     */
    public function getClientIpAddress(string $clientId): ?string
    {
        if (!isset($this->clients[$clientId]) || !is_resource($this->clients[$clientId])) {
            return null;
        }

        $peerName = stream_socket_get_name($this->clients[$clientId], true);
        if ($peerName === false) {
            return null;
        }

        // Extract IP from the peer name (format: "ip:port")
        $parts = explode(':', $peerName);
        return $parts[0];
    }

    /**
     * Register async task processors
     *
     * @return void
     */
    protected function registerTaskProcessors(): void
    {
        // Database operations
        $this->taskQueue->registerProcessor('db_write', function (array $data, array $task) {
            // Queue database writes to avoid blocking main thread
            $this->logger->debug("[Async Task] Processing DB write", ['data' => $data]);
            // Implement actual database write here
            return true;
        });

        // File operations
        $this->taskQueue->registerProcessor('file_write', function (array $data, array $task) {
            try {
                if (isset($data['path']) && is_string($data['path']) && isset($data['content'])) {
                    file_put_contents($data['path'], $data['content']);
                    return true;
                }
            } catch (Throwable $e) {
                $this->logger->exception($e, ['context' => 'Async file write']);
            }
            return false;
        });

        // Log processing
        $this->taskQueue->registerProcessor('log_process', function (array $data, array $task) {
            // Process logs asynchronously
            $this->logger->debug("[Async Task] Processing log", ['data' => $data]);
            return true;
        });

        // External API calls
        $this->taskQueue->registerProcessor('api_call', function (array $data, array $task) {
            try {
                if (isset($data['url']) && is_string($data['url'])) {
                    // Make external API calls without blocking
                    $method = isset($data['method']) && is_string($data['method']) ? $data['method'] : 'GET';
                    $context = stream_context_create([
                        'http' => [
                            'timeout' => 5,
                            'method' => $method,
                        ],
                    ]);

                    $result = @file_get_contents($data['url'], false, $context);
                    return $result !== false;
                }
            } catch (Throwable $e) {
                $this->logger->exception($e, ['context' => 'Async API call']);
            }
            return false;
        });
    }

    /**
     * Update performance metrics
     *
     * @return void
     */
    protected function updatePerformanceMetrics(): void
    {
        try {
            // Connection statistics
            $connectionStats = [
                'active_connections' => count($this->clients),
                'total_accepted' => count($this->clients), // This should be tracked separately
                'total_closed' => 0, // This should be tracked separately
            ];

            $this->performanceMonitor->updateConnectionStats($connectionStats);

            // Update with connection pool and task queue stats
            $this->performanceMonitor->collectSystemMetrics();
        } catch (Throwable $e) {
            $this->logger->exception($e, ['context' => 'Performance metrics update']);
        }
    }

    /**
     * Queue an async task
     *
     * @param string $type Task type
     * @param array<string, mixed> $data Task data
     * @param int $priority Priority level
     * @return void
     */
    public function queueAsyncTask(string $type, array $data, int $priority = 0): void
    {
        $this->taskQueue->queueTask($type, $data, $priority);
    }

    /**
     * Get comprehensive server statistics
     *
     * @return array<string, mixed>
     */
    public function getServerStats(): array
    {
        return [
            'performance' => $this->performanceMonitor->getMetrics(),
            'connection_pool' => $this->connectionPool->getStats(),
            'task_queue' => $this->taskQueue->getStats(),
            'server_info' => [
                'uptime' => $this->performanceMonitor->getFormattedUptime(),
                'active_clients' => count($this->clients),
                'client_types' => array_count_values($this->clientTypes),
                'pending_tasks' => $this->taskQueue->getPendingCount(),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ],
        ];
    }

    /**
     * Record request for performance monitoring
     *
     * @param string $type Request type (http/websocket)
     * @param float $responseTime Response time in milliseconds
     * @return void
     */
    public function recordRequestMetric(string $type, float $responseTime = 0): void
    {
        $this->performanceMonitor->recordRequest($type, $responseTime);
    }

    /**
     * Record error for monitoring
     *
     * @param string $type Error type (connection/request)
     * @return void
     */
    public function recordErrorMetric(string $type): void
    {
        $this->performanceMonitor->recordError($type);
    }
}
