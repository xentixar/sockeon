<?php
/**
 * Performance Monitor
 * 
 * Tracks server performance metrics and resource usage
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core;

class PerformanceMonitor
{
    /**
     * Performance metrics
     * @var array<string, mixed>
     */
    protected array $metrics = [];

    /**
     * Metric history for trend analysis
     * @var array<string, array>
     */
    protected array $history = [];

    /**
     * Start time for uptime calculation
     * @var float
     */
    protected float $startTime;

    /**
     * Last collection time
     * @var float
     */
    protected float $lastCollection;

    /**
     * Collection interval in seconds
     * @var int
     */
    protected int $collectionInterval = 10;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->lastCollection = microtime(true);
        $this->initializeMetrics();
    }

    /**
     * Initialize base metrics
     * 
     * @return void
     */
    protected function initializeMetrics(): void
    {
        $this->metrics = [
            'server' => [
                'uptime_seconds' => 0,
                'memory_usage_mb' => 0,
                'memory_peak_mb' => 0,
                'cpu_usage_percent' => 0
            ],
            'connections' => [
                'active_count' => 0,
                'total_accepted' => 0,
                'total_closed' => 0,
                'connections_per_second' => 0
            ],
            'requests' => [
                'http_total' => 0,
                'websocket_total' => 0,
                'requests_per_second' => 0,
                'average_response_time_ms' => 0
            ],
            'errors' => [
                'connection_errors' => 0,
                'request_errors' => 0,
                'error_rate_percent' => 0
            ]
        ];
    }

    /**
     * Update connection metrics
     * 
     * @param array<string, mixed> $stats Connection statistics
     * @return void
     */
    public function updateConnectionStats(array $stats): void
    {
        $this->metrics['connections']['active_count'] = $stats['active_connections'] ?? 0;
        $this->metrics['connections']['total_accepted'] = $stats['total_accepted'] ?? 0;
        $this->metrics['connections']['total_closed'] = $stats['total_closed'] ?? 0;
        
        // Calculate connections per second
        $this->calculateRate('connections_per_second', $this->metrics['connections']['total_accepted']);
    }

    /**
     * Update request metrics
     * 
     * @param string $type Request type (http/websocket)
     * @param float $responseTime Response time in milliseconds
     * @return void
     */
    public function recordRequest(string $type, float $responseTime = 0): void
    {
        if ($type === 'http') {
            $this->metrics['requests']['http_total']++;
        } elseif ($type === 'websocket') {
            $this->metrics['requests']['websocket_total']++;
        }

        // Update average response time
        $totalRequests = $this->metrics['requests']['http_total'] + $this->metrics['requests']['websocket_total'];
        $currentAvg = $this->metrics['requests']['average_response_time_ms'];
        
        if ($totalRequests > 0) {
            $this->metrics['requests']['average_response_time_ms'] = 
                (($currentAvg * ($totalRequests - 1)) + $responseTime) / $totalRequests;
        } else {
            $this->metrics['requests']['average_response_time_ms'] = $responseTime;
        }

        // Calculate requests per second
        $this->calculateRate('requests_per_second', $totalRequests);
    }

    /**
     * Record an error
     * 
     * @param string $type Error type (connection/request)
     * @return void
     */
    public function recordError(string $type): void
    {
        if ($type === 'connection') {
            $this->metrics['errors']['connection_errors']++;
        } elseif ($type === 'request') {
            $this->metrics['errors']['request_errors']++;
        }

        // Calculate error rate
        $totalRequests = $this->metrics['requests']['http_total'] + $this->metrics['requests']['websocket_total'];
        $totalErrors = $this->metrics['errors']['connection_errors'] + $this->metrics['errors']['request_errors'];
        
        if ($totalRequests > 0) {
            $this->metrics['errors']['error_rate_percent'] = ($totalErrors / $totalRequests) * 100;
        }
    }

    /**
     * Collect system metrics
     * 
     * @return void
     */
    public function collectSystemMetrics(): void
    {
        $currentTime = microtime(true);
        
        // Only collect if interval has passed
        if ($currentTime - $this->lastCollection < $this->collectionInterval) {
            return;
        }

        // Update uptime
        $this->metrics['server']['uptime_seconds'] = $currentTime - $this->startTime;

        // Memory usage
        $this->metrics['server']['memory_usage_mb'] = memory_get_usage(true) / 1024 / 1024;
        $this->metrics['server']['memory_peak_mb'] = memory_get_peak_usage(true) / 1024 / 1024;

        // CPU usage (simple approximation)
        $this->metrics['server']['cpu_usage_percent'] = $this->getCpuUsage();

        // Store history for trends
        $this->storeHistory();
        
        $this->lastCollection = $currentTime;
    }

    /**
     * Calculate rate per second
     * 
     * @param string $key Metric key
     * @param int $total Total count
     * @return void
     */
    protected function calculateRate(string $key, int $total): void
    {
        $uptime = microtime(true) - $this->startTime;
        if ($uptime > 0) {
            if ($key === 'connections_per_second') {
                $this->metrics['connections'][$key] = $total / $uptime;
            } elseif ($key === 'requests_per_second') {
                $this->metrics['requests'][$key] = $total / $uptime;
            }
        }
    }

    /**
     * Get CPU usage approximation
     * 
     * @return float CPU usage percentage
     */
    protected function getCpuUsage(): float
    {
        static $lastCpuTime = null;
        static $lastTime = null;

        if (function_exists('getrusage')) {
            $usage = getrusage();
            $currentCpuTime = $usage['ru_utime.tv_sec'] * 1000000 + $usage['ru_utime.tv_usec'] + 
                             $usage['ru_stime.tv_sec'] * 1000000 + $usage['ru_stime.tv_usec'];
            $currentTime = microtime(true);

            if ($lastCpuTime !== null && $lastTime !== null) {
                $cpuDelta = $currentCpuTime - $lastCpuTime;
                $timeDelta = ($currentTime - $lastTime) * 1000000;
                
                $lastCpuTime = $currentCpuTime;
                $lastTime = $currentTime;
                
                return min(100, ($cpuDelta / $timeDelta) * 100);
            }

            $lastCpuTime = $currentCpuTime;
            $lastTime = $currentTime;
        }

        return 0.0;
    }

    /**
     * Store metrics history
     * 
     * @return void
     */
    protected function storeHistory(): void
    {
        $timestamp = time();
        $maxHistoryPoints = 100; // Keep last 100 data points

        foreach ($this->metrics as $category => $categoryMetrics) {
            if (!isset($this->history[$category])) {
                $this->history[$category] = [];
            }

            foreach ($categoryMetrics as $key => $value) {
                if (!isset($this->history[$category][$key])) {
                    $this->history[$category][$key] = [];
                }

                $this->history[$category][$key][$timestamp] = $value;

                // Limit history size
                if (count($this->history[$category][$key]) > $maxHistoryPoints) {
                    $this->history[$category][$key] = array_slice(
                        $this->history[$category][$key], 
                        -$maxHistoryPoints, 
                        null, 
                        true
                    );
                }
            }
        }
    }

    /**
     * Get current metrics
     * 
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        $this->collectSystemMetrics();
        return $this->metrics;
    }

    /**
     * Get metrics with history
     * 
     * @return array<string, mixed>
     */
    public function getMetricsWithHistory(): array
    {
        return [
            'current' => $this->getMetrics(),
            'history' => $this->history
        ];
    }

    /**
     * Reset all metrics
     * 
     * @return void
     */
    public function reset(): void
    {
        $this->startTime = microtime(true);
        $this->lastCollection = microtime(true);
        $this->initializeMetrics();
        $this->history = [];
    }

    /**
     * Get formatted uptime string
     * 
     * @return string
     */
    public function getFormattedUptime(): string
    {
        $seconds = $this->metrics['server']['uptime_seconds'];
        
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = floor($seconds % 60);

        return sprintf('%dd %dh %dm %ds', $days, $hours, $minutes, $seconds);
    }
}