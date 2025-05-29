<?php
/**
 * Logger class
 *
 * Implements PSR-3 logging standards
 *
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Logging;

use DateTime;
use DateTimeInterface;

class Logger implements LoggerInterface
{
    /**
     * Directory path where log files will be stored
     * @var string
     */
    protected string $logDirectory;

    /**
     * Current minimum log level
     * @var string
     */
    protected string $minLogLevel;

    /**
     * Whether to output logs to the console
     * @var bool
     */
    protected bool $logToConsole;

    /**
     * Whether to create separate log files for each level
     * @var bool
     */
    protected bool $separateLogFiles;

    /**
     * ANSI color codes for console output
     * @var array<string, string>
     */
    protected array $colors = [
        LogLevel::EMERGENCY => "\033[1;37;41m", // White on Red background (bold)
        LogLevel::ALERT     => "\033[1;31;40m", // Red on Black background (bold)
        LogLevel::CRITICAL  => "\033[1;31m",    // Red (bold)
        LogLevel::ERROR     => "\033[0;31m",    // Red
        LogLevel::WARNING   => "\033[0;33m",    // Yellow
        LogLevel::NOTICE    => "\033[0;36m",    // Cyan
        LogLevel::INFO      => "\033[0;32m",    // Green
        LogLevel::DEBUG     => "\033[0;90m",    // Gray
    ];

    /**
     * Create a new Logger instance
     *
     * @param string|null $logDirectory Directory where log files will be stored
     * @param string $minLogLevel Minimum log level to record
     * @param bool $logToConsole Whether to output logs to console
     * @param bool $separateLogFiles Whether to create separate files for each level
     */
    public function __construct(
        ?string $logDirectory = null,
        string $minLogLevel = LogLevel::DEBUG,
        bool $logToConsole = true,
        bool $separateLogFiles = true
    ) {
        $this->logDirectory = $logDirectory ?? dirname(__DIR__, 2) . '/logs';
        $this->minLogLevel = $minLogLevel;
        $this->logToConsole = $logToConsole;
        $this->separateLogFiles = $separateLogFiles;

        if (!file_exists($this->logDirectory)) {
            mkdir($this->logDirectory, 0755, true);
        }
    }

    /**
     * Log a message with emergency level
     *
     * System is unusable
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Log a message with alert level
     *
     * Action must be taken immediately
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Log a message with critical level
     *
     * Critical conditions
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Log a message with error level
     *
     * Error conditions
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Log a message with warning level
     *
     * Warning conditions
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Log a message with notice level
     *
     * Normal but significant conditions
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Log a message with info level
     *
     * Informational messages
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Log a message with debug level
     *
     * Debug-level messages
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Log a message with the specified log level
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if ($this->shouldLog($level)) {
            $logEntry = $this->formatLogEntry($level, $message, $context);

            $this->writeToFile($level, $logEntry);

            if ($this->logToConsole) {
                $this->writeToConsole($level, $logEntry);
            }
        }
    }

    /**
     * Check if a message with the given level should be logged
     *
     * @param string $level Log level to check
     * @return bool Whether the message should be logged
     */
    protected function shouldLog(string $level): bool
    {
        return LogLevel::toInt($level) >= LogLevel::toInt($this->minLogLevel);
    }

    /**
     * Format a log entry
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     * @return string Formatted log entry
     */
    protected function formatLogEntry(string $level, string $message, array $context = []): string
    {
        $timestamp = (new DateTime())->format('Y-m-d H:i:s');
        $levelString = strtoupper($level);
        $contextString = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';

        return "[$timestamp] $levelString: $message$contextString";
    }

    /**
     * Write a log entry to a file
     *
     * @param string $level Log level
     * @param string $logEntry Formatted log entry
     */
    protected function writeToFile(string $level, string $logEntry): void
    {
        $date = (new DateTime())->format('Y-m-d');

        $mainLogFile = "{$this->logDirectory}/sockeon-{$date}.log";
        file_put_contents($mainLogFile, $logEntry . PHP_EOL, FILE_APPEND);

        if ($this->separateLogFiles) {
            $levelDir = "{$this->logDirectory}/{$level}";

            if (!file_exists($levelDir)) {
                mkdir($levelDir, 0755, true);
            }

            $levelLogFile = "{$levelDir}/{$date}.log";
            file_put_contents($levelLogFile, $logEntry . PHP_EOL, FILE_APPEND);
        }
    }

    /**
     * Write a log entry to the console with appropriate colors
     *
     * @param string $level Log level
     * @param string $logEntry Formatted log entry
     */
    protected function writeToConsole(string $level, string $logEntry): void
    {
        $colorCode = $this->colors[$level] ?? "\033[0m";
        $resetColor = "\033[0m";

        $coloredLogEntry = $colorCode . $logEntry . $resetColor;
        echo $coloredLogEntry . PHP_EOL;
    }

    /**
     * Set the minimum log level
     *
     * @param string $level Log level
     */
    public function setMinLogLevel(string $level): void
    {
        $this->minLogLevel = $level;
    }

    /**
     * Enable or disable console output
     *
     * @param bool $logToConsole Whether to output logs to console
     */
    public function setLogToConsole(bool $logToConsole): void
    {
        $this->logToConsole = $logToConsole;
    }

    /**
     * Set the log directory
     *
     * @param string $directory Log directory path
     */
    public function setLogDirectory(string $directory): void
    {
        $this->logDirectory = $directory;

        if (!file_exists($this->logDirectory)) {
            mkdir($this->logDirectory, 0755, true);
        }
    }

    /**
     * Enable or disable separate log files for each level
     *
     * @param bool $separateLogFiles Whether to create separate files for each level
     */
    public function setSeparateLogFiles(bool $separateLogFiles): void
    {
        $this->separateLogFiles = $separateLogFiles;
    }
}
