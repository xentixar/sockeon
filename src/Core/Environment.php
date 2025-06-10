<?php
/**
 * Environment class
 * 
 * Handles reading from .env file and providing environment variables
 * for configuration throughout the application
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core;

class Environment
{
    /**
     * Environment values loaded from .env file
     * @var array<string, string>
     */
    private static array $values = [];
    
    /**
     * Whether the environment has been loaded
     * @var bool
     */
    private static bool $loaded = false;
    
    /**
     * Default values for environment variables
     * @var array<string, string>
     */
    private static array $defaults = [
        'SOCKEON_SERVER_HOST' => '0.0.0.0',
        'SOCKEON_SERVER_PORT' => '6001',
        'SOCKEON_CLIENT_HOST' => 'localhost',
        'SOCKEON_CLIENT_PORT' => '6001',
        'SOCKEON_BROADCAST_SALT' => 'sockeon-broadcast-salt-20dd23f',
        'SOCKEON_TOKEN_EXPIRATION' => '30',
    ];
    
    /**
     * Load environment variables from .env file
     * 
     * @param string|null $path Optional path to .env file
     * @return void
     */
    public static function load(?string $path = null): void
    {
        if (self::$loaded) {
            return;
        }
        
        if ($path === null) {
            $path = self::findEnvFile();
        }
        
        if ($path !== null && file_exists($path)) {
            self::parseEnvFile($path);
        }
        
        self::$loaded = true;
    }
    
    /**
     * Find the .env file by looking in typical locations
     * 
     * @return string|null Path to .env file if found, null otherwise
     */
    private static function findEnvFile(): ?string
    {
        $possiblePaths = [
            getcwd() . '/.env',
            dirname(__DIR__, 2) . '/.env',
            dirname(getcwd()) . '/.env',
            dirname(dirname(getcwd())) . '/.env',
            dirname(dirname(dirname(getcwd()))) . '/.env',
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }
    
    /**
     * Parse the .env file and load variables
     * 
     * @param string $path Path to .env file
     * @return void
     */
    private static function parseEnvFile(string $path): void
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
                    $value = $matches[2];
                }
                
                self::$values[$key] = $value;
            }
        }
    }
    
    /**
     * Get an environment variable
     * 
     * @param string $key The environment variable name
     * @param string|null $default Optional default value
     * @return string|null The environment variable value or default
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();
        
        if (isset(self::$values[$key])) {
            return self::$values[$key];
        }
        
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        if (isset(self::$defaults[$key])) {
            return self::$defaults[$key];
        }
        
        return $default;
    }
    
    /**
     * Set an environment variable
     * 
     * @param string $key The environment variable name
     * @param string $value The environment variable value
     * @return void
     */
    public static function set(string $key, string $value): void
    {
        self::$values[$key] = $value;
    }
}
