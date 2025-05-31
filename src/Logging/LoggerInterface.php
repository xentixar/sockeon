<?php
/**
 * LoggerInterface
 * 
 * Defines the standard interface for logging operations according to PSR-3.
 * Provides methods for logging at different severity levels.
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Logging;

use Throwable;

interface LoggerInterface
{
    /**
     * Log a message with emergency level
     * 
     * System is unusable
     * 
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function emergency(string $message, array $context = []): void;

    /**
     * Log a message with alert level
     *
     * Action must be taken immediately
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function alert(string $message, array $context = []): void;

    /**
     * Log a message with critical level
     *
     * Critical conditions
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function critical(string $message, array $context = []): void;

    /**
     * Log a message with error level
     *
     * Error conditions
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function error(string $message, array $context = []): void;

    /**
     * Log a message with warning level
     *
     * Warning conditions
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Log a message with notice level
     *
     * Normal but significant conditions
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function notice(string $message, array $context = []): void;

    /**
     * Log a message with info level
     *
     * Informational messages
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function info(string $message, array $context = []): void;

    /**
     * Log a message with debug level
     *
     * Debug-level messages
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function debug(string $message, array $context = []): void;

    /**
     * Log an exception with full traceback information
     *
     * @param Throwable $exception The exception to log
     * @param array<string, mixed> $context Additional context data
     * @param string $level Log level, defaults to error
     * @return void
     */
    public function exception(Throwable $exception, array $context = [], string $level = LogLevel::ERROR): void;
    
    /**
     * Log a message with arbitrary level
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void;
}
