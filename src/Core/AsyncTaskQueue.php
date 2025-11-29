<?php
/**
 * Async Task Queue
 * 
 * Handles asynchronous task processing for heavy operations
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core;

use SplQueue;
use SplPriorityQueue;
use Throwable;

class AsyncTaskQueue
{
    /**
     * High priority task queue
     * @var SplPriorityQueue
     */
    protected SplPriorityQueue $priorityQueue;

    /**
     * Regular task queue
     * @var SplQueue
     */
    protected SplQueue $regularQueue;

    /**
     * Task execution metrics
     * @var array<string, int>
     */
    protected array $metrics = [
        'total_queued' => 0,
        'total_processed' => 0,
        'total_failed' => 0,
        'processing_time_ms' => 0
    ];

    /**
     * Maximum tasks to process per cycle
     * @var int
     */
    protected int $maxTasksPerCycle;

    /**
     * Task processors by type
     * @var array<string, callable>
     */
    protected array $processors = [];

    public function __construct(int $maxTasksPerCycle = 10)
    {
        $this->priorityQueue = new SplPriorityQueue();
        $this->regularQueue = new SplQueue();
        $this->maxTasksPerCycle = $maxTasksPerCycle;
    }

    /**
     * Queue a task for async processing
     * 
     * @param string $type Task type
     * @param array<string, mixed> $data Task data
     * @param int $priority Priority (higher = more important)
     * @return void
     */
    public function queueTask(string $type, array $data, int $priority = 0): void
    {
        $task = [
            'id' => uniqid('task_', true),
            'type' => $type,
            'data' => $data,
            'queued_at' => microtime(true),
            'attempts' => 0
        ];

        if ($priority > 0) {
            $this->priorityQueue->insert($task, $priority);
        } else {
            $this->regularQueue->enqueue($task);
        }

        $this->metrics['total_queued']++;
    }

    /**
     * Register a task processor
     * 
     * @param string $type Task type
     * @param callable $processor Processing function
     * @return void
     */
    public function registerProcessor(string $type, callable $processor): void
    {
        $this->processors[$type] = $processor;
    }

    /**
     * Process queued tasks
     * 
     * @return int Number of tasks processed
     */
    public function processTasks(): int
    {
        $processed = 0;
        $startTime = microtime(true);

        // Process high priority tasks first
        while (!$this->priorityQueue->isEmpty() && $processed < $this->maxTasksPerCycle) {
            $task = $this->priorityQueue->extract();
            if ($this->processTask($task)) {
                $processed++;
            }
        }

        // Process regular tasks
        while (!$this->regularQueue->isEmpty() && $processed < $this->maxTasksPerCycle) {
            $task = $this->regularQueue->dequeue();
            if ($this->processTask($task)) {
                $processed++;
            }
        }

        // Update metrics
        $this->metrics['total_processed'] += $processed;
        $this->metrics['processing_time_ms'] += (microtime(true) - $startTime) * 1000;

        return $processed;
    }

    /**
     * Process a single task
     * 
     * @param array $task Task info
     * @return bool Success
     */
    protected function processTask(array $task): bool
    {
        try {
            $task['attempts']++;

            if (!isset($this->processors[$task['type']])) {
                $this->metrics['total_failed']++;
                return false;
            }

            $processor = $this->processors[$task['type']];
            $result = $processor($task['data'], $task);

            return $result !== false;
        } catch (Throwable $e) {
            $this->metrics['total_failed']++;
            
            // Retry failed tasks (max 3 attempts)
            if ($task['attempts'] < 3) {
                $this->regularQueue->enqueue($task);
            }
            
            return false;
        }
    }

    /**
     * Get queue statistics
     * 
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return [
            'metrics' => $this->metrics,
            'queue_sizes' => [
                'priority' => $this->priorityQueue->count(),
                'regular' => $this->regularQueue->count()
            ],
            'processors' => array_keys($this->processors)
        ];
    }

    /**
     * Get pending task count
     * 
     * @return int
     */
    public function getPendingCount(): int
    {
        return $this->priorityQueue->count() + $this->regularQueue->count();
    }

    /**
     * Clear all queued tasks
     * 
     * @return void
     */
    public function clear(): void
    {
        $this->priorityQueue = new SplPriorityQueue();
        $this->regularQueue = new SplQueue();
    }
}