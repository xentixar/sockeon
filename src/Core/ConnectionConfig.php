<?php
/**
 * ConnectionConfig class
 * 
 * Provides connection configuration for server and client
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core;

class ConnectionConfig
{
    /**
     * Get server host from environment
     * 
     * @return string The server host
     */
    public static function getServerHost(): string
    {
        return Environment::get('SOCKEON_SERVER_HOST') ?? '0.0.0.0';
    }
    
    /**
     * Get server port from environment
     * 
     * @return int The server port
     */
    public static function getServerPort(): int
    {
        $port = Environment::get('SOCKEON_SERVER_PORT');
        return $port !== null ? (int) $port : 6001;
    }
}
