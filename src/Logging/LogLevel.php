<?php

/**
 * LogLevel class
 *
 * Defines standard logging levels according to PSR-3 logging standards.
 * Provides methods to convert between level names and severity integers.
 *
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Logging;

class LogLevel
{
    /**
     * System is unusable
     */
    public const EMERGENCY = 'emergency';

    /**
     * Action must be taken immediately
     */
    public const ALERT = 'alert';

    /**
     * Critical conditions
     */
    public const CRITICAL = 'critical';

    /**
     * Error conditions
     */
    public const ERROR = 'error';

    /**
     * Warning conditions
     */
    public const WARNING = 'warning';

    /**
     * Normal but significant conditions
     */
    public const NOTICE = 'notice';

    /**
     * Informational messages
     */
    public const INFO = 'info';

    /**
     * Debug-level messages
     */
    public const DEBUG = 'debug';

    /**
     * Convert level name to integer severity
     * Higher number = higher severity
     *
     * @param string $level The log level name
     * @return int The severity value (0-7)
     */
    public static function toInt(string $level): int
    {
        return match ($level) {
            self::INFO => 1,
            self::NOTICE => 2,
            self::WARNING => 3,
            self::ERROR => 4,
            self::CRITICAL => 5,
            self::ALERT => 6,
            self::EMERGENCY => 7,
            default => 0
        };
    }
}
