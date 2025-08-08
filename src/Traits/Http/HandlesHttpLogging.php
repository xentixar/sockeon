<?php
/**
 * HandlesHttpLogging trait
 * 
 * Manages HTTP request logging and debugging
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Traits\Http;

use Throwable;

trait HandlesHttpLogging
{
    /**
     * Log debug information if debug mode is enabled
     * 
     * @param string $message The debug message
     * @param mixed|null $data Additional data to log
     * @return void
     */
    protected function debug(string $message, mixed $data = null): void
    {
        try {
            $dataString = $data !== null ? ' ' . json_encode($data) : '';
            $this->server->getLogger()->debug("[Sockeon HTTP] {$message}{$dataString}");
        } catch (Throwable $e) {
            $this->server->getLogger()->error("Failed to log message: " . $e->getMessage());
        }
    }
}
