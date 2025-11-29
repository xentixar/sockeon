<?php
/**
 * Connection Pool Manager
 * 
 * Manages connection reuse and pooling for better resource utilization
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core;

use SplQueue;
use Throwable;

class ConnectionPool
{
    /**
     * Pool of available connections organized by type
     * @var array<string, SplQueue>
     */
    protected array $pools = [];

    /**
     * Active connections being used
     * @var array<int, array>
     */
    protected array $activeConnections = [];

    /**
     * Connection metrics
     * @var array<string, int>
     */
    protected array $metrics = [
        'total_created' => 0,
        'total_reused' => 0,
        'total_released' => 0,
        'active_count' => 0
    ];

    /**
     * Maximum connections per pool type
     * @var int
     */
    protected int $maxPoolSize;

    /**
     * Connection timeout in seconds
     * @var int
     */
    protected int $connectionTimeout;

    public function __construct(int $maxPoolSize = 100, int $connectionTimeout = 300)
    {
        $this->maxPoolSize = $maxPoolSize;
        $this->connectionTimeout = $connectionTimeout;
    }

    /**
     * Get a connection from the pool or create a new one
     * 
     * @param string $type Connection type (ws, http)
     * @param int $clientId Client identifier
     * @param resource $resource Socket resource
     * @return array Connection info
     */
    public function acquireConnection(string $type, int $clientId, $resource): array
    {
        $connection = [
            'id' => $clientId,
            'type' => $type,
            'resource' => $resource,
            'created_at' => microtime(true),
            'last_used' => microtime(true),
            'reuse_count' => 0
        ];

        // Check if we can reuse a connection from the pool
        if (isset($this->pools[$type]) && !$this->pools[$type]->isEmpty()) {
            $pooledConnection = $this->pools[$type]->dequeue();
            
            // Validate the pooled connection
            if ($this->isConnectionValid($pooledConnection)) {
                $pooledConnection['id'] = $clientId;
                $pooledConnection['resource'] = $resource;
                $pooledConnection['last_used'] = microtime(true);
                $pooledConnection['reuse_count']++;
                
                $this->activeConnections[$clientId] = $pooledConnection;
                $this->metrics['total_reused']++;
                $this->metrics['active_count']++;
                
                return $pooledConnection;
            }
        }

        // Create new connection
        $this->activeConnections[$clientId] = $connection;
        $this->metrics['total_created']++;
        $this->metrics['active_count']++;

        return $connection;
    }

    /**
     * Release a connection back to the pool
     * 
     * @param int $clientId Client identifier
     * @return void
     */
    public function releaseConnection(int $clientId): void
    {
        if (!isset($this->activeConnections[$clientId])) {
            return;
        }

        $connection = $this->activeConnections[$clientId];
        unset($this->activeConnections[$clientId]);
        $this->metrics['active_count']--;
        $this->metrics['total_released']++;

        $type = $connection['type'];

        // Don't pool if connection is too old or has been reused too much
        if ($connection['reuse_count'] >= 50 || 
            (microtime(true) - $connection['created_at']) > $this->connectionTimeout) {
            return;
        }

        // Initialize pool if needed
        if (!isset($this->pools[$type])) {
            $this->pools[$type] = new SplQueue();
        }

        // Add to pool if not full
        if ($this->pools[$type]->count() < $this->maxPoolSize) {
            $connection['pooled_at'] = microtime(true);
            $this->pools[$type]->enqueue($connection);
        }
    }

    /**
     * Check if a pooled connection is still valid
     * 
     * @param array $connection Connection info
     * @return bool True if valid
     */
    protected function isConnectionValid(array $connection): bool
    {
        // Check age
        if ((microtime(true) - ($connection['pooled_at'] ?? 0)) > 30) {
            return false;
        }

        // Check reuse count
        if ($connection['reuse_count'] >= 50) {
            return false;
        }

        return true;
    }

    /**
     * Clean up expired connections from pools
     * 
     * @return void
     */
    public function cleanup(): void
    {
        foreach ($this->pools as $type => $pool) {
            $validConnections = new SplQueue();
            
            while (!$pool->isEmpty()) {
                $connection = $pool->dequeue();
                if ($this->isConnectionValid($connection)) {
                    $validConnections->enqueue($connection);
                }
            }
            
            $this->pools[$type] = $validConnections;
        }
    }

    /**
     * Get pool statistics
     * 
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $poolSizes = [];
        foreach ($this->pools as $type => $pool) {
            $poolSizes[$type] = $pool->count();
        }

        return [
            'metrics' => $this->metrics,
            'pool_sizes' => $poolSizes,
            'total_pooled' => array_sum($poolSizes)
        ];
    }
}