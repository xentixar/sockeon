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
     * @var array<string, SplQueue<array<string, mixed>>>
     */
    protected array $pools = [];

    /**
     * Active connections being used
     * @var array<string, array<string, mixed>>
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
        'active_count' => 0,
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
     * @param string $clientId Client identifier
     * @param resource $resource Socket resource
     * @return array<string, mixed> Connection info
     */
    public function acquireConnection(string $type, string $clientId, $resource): array
    {
        $connection = [
            'id' => $clientId,
            'type' => $type,
            'resource' => $resource,
            'created_at' => microtime(true),
            'last_used' => microtime(true),
            'reuse_count' => 0,
        ];

        // Check if we can reuse a connection from the pool
        if (isset($this->pools[$type]) && !$this->pools[$type]->isEmpty()) {
            $pooledConnection = $this->pools[$type]->dequeue();

            // Validate the pooled connection
            if ($this->isConnectionValid($pooledConnection)) {
                $pooledConnection['id'] = $clientId;
                $pooledConnection['resource'] = $resource;
                $pooledConnection['last_used'] = microtime(true);
                $reuseCount = isset($pooledConnection['reuse_count']) && is_int($pooledConnection['reuse_count']) ? $pooledConnection['reuse_count'] : 0;
                $pooledConnection['reuse_count'] = $reuseCount + 1;

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
     * @param string $clientId Client identifier
     * @return void
     */
    public function releaseConnection(string $clientId): void
    {
        if (!isset($this->activeConnections[$clientId])) {
            return;
        }

        $connection = $this->activeConnections[$clientId];
        unset($this->activeConnections[$clientId]);
        $this->metrics['active_count']--;
        $this->metrics['total_released']++;

        $type = isset($connection['type']) && is_string($connection['type']) ? $connection['type'] : 'unknown';
        $reuseCount = isset($connection['reuse_count']) && is_int($connection['reuse_count']) ? $connection['reuse_count'] : 0;
        $createdAt = isset($connection['created_at']) && is_float($connection['created_at']) ? $connection['created_at'] : microtime(true);

        // Don't pool if connection is too old or has been reused too much
        if ($reuseCount >= 50
            || (microtime(true) - $createdAt) > $this->connectionTimeout) {
            return;
        }

        // Initialize pool if needed
        if (!isset($this->pools[$type])) {
            /** @var SplQueue<array<string, mixed>> $newQueue */
            $newQueue = new SplQueue();
            $this->pools[$type] = $newQueue;
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
     * @param array<string, mixed> $connection Connection info
     * @return bool True if valid
     */
    protected function isConnectionValid(array $connection): bool
    {
        // Check age
        $pooledAt = isset($connection['pooled_at']) && is_float($connection['pooled_at']) ? $connection['pooled_at'] : 0.0;
        if ((microtime(true) - $pooledAt) > 30) {
            return false;
        }

        // Check reuse count
        $reuseCount = isset($connection['reuse_count']) && is_int($connection['reuse_count']) ? $connection['reuse_count'] : 0;
        if ($reuseCount >= 50) {
            return false;
        }

        return true;
    }

    /**
     * Clean up expired connections from pools
     * Optimized to reduce memory fragmentation by reusing queue structure
     *
     * @return void
     */
    public function cleanup(): void
    {
        foreach ($this->pools as $type => $pool) {
            $validCount = 0;
            $totalCount = $pool->count();

            // If pool is empty or all connections are valid, skip cleanup
            if ($totalCount === 0) {
                continue;
            }

            // Check if any connections need cleanup
            $needsCleanup = false;
            $tempConnections = [];
            while (!$pool->isEmpty()) {
                $connection = $pool->dequeue();
                if ($this->isConnectionValid($connection)) {
                    $tempConnections[] = $connection;
                    $validCount++;
                } else {
                    $needsCleanup = true;
                }
            }

            // Only rebuild queue if cleanup is needed to reduce fragmentation
            if ($needsCleanup) {
                /** @var SplQueue<array<string, mixed>> $validConnections */
                $validConnections = new SplQueue();
                foreach ($tempConnections as $connection) {
                    $validConnections->enqueue($connection);
                }
                $this->pools[$type] = $validConnections;
            } else {
                // Rebuild queue with same connections to maintain structure
                foreach ($tempConnections as $connection) {
                    $pool->enqueue($connection);
                }
            }
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
            'total_pooled' => array_sum($poolSizes),
        ];
    }
}
