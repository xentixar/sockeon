<?php
/**
 * BroadcastAuthenticator class
 * 
 * Handles authentication for broadcast events to ensure they can only
 * be fired from the official Sockeon Client library
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core\Security;

class BroadcastAuthenticator
{
    /**
     * Generate a broadcast authentication token
     * 
     * @param string $clientId Unique identifier for the client instance
     * @param int $timestamp Current timestamp
     * @return string The generated token
     */
    public static function generateToken(string $clientId, int $timestamp): string
    {
        $salt = SecurityConfig::getBroadcastSalt();
        $data = $clientId . $timestamp . $salt;
        return hash('sha256', $data);
    }
    
    /**
     * Validate a broadcast authentication token
     * 
     * @param string $token The token to validate
     * @param string $clientId Client identifier used to generate the token
     * @param int $timestamp Timestamp when the token was generated
     * @param int $expirationSeconds Maximum token age in seconds
     * @return bool Whether the token is valid
     */
    public static function validateToken(string $token, string $clientId, int $timestamp, ?int $expirationSeconds = null): bool
    {
        $expiration = $expirationSeconds ?? SecurityConfig::getTokenExpiration();
        
        $currentTime = time();
        if ($currentTime - $timestamp > $expiration) {
            return false;
        }
        
        $expectedToken = self::generateToken($clientId, $timestamp);
        
        return hash_equals($expectedToken, $token);
    }
}
