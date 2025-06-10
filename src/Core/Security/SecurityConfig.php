<?php
/**
 * SecurityConfig class
 * 
 * Provides security configuration for the application
 * including broadcast salt and token settings
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core\Security;

use Sockeon\Sockeon\Core\Environment;

class SecurityConfig
{
    /**
     * Get the broadcast salt used for authentication
     * 
     * @return string The broadcast salt value
     */
    public static function getBroadcastSalt(): string
    {
        return Environment::get('SOCKEON_BROADCAST_SALT') ?? 'sockeon-broadcast-salt-20dd23f';
    }
    
    /**
     * Get token expiration time in seconds
     * 
     * @return int The token expiration time in seconds
     */
    public static function getTokenExpiration(): int
    {
        $expiration = Environment::get('SOCKEON_TOKEN_EXPIRATION');
        return $expiration !== null ? (int) $expiration : 30;
    }
}
