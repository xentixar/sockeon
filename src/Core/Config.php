<?php

/**
 * Configuration class for Sockeon
 * 
 * Manages global configuration settings for the Sockeon server
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core;

class Config
{
    /**
     * Global configuration values
     * @var array<string, string|array<int|string, string>|bool|null>
     */
    private static array $config = [];

    /**
     * Initialize the configuration with default values
     */
    public static function init(): void
    {
        if (empty(self::$config)) {
            self::$config = [
                'queue_file' => self::getDefaultQueueFilePath(),
                'auth_key' => null,
                'trust_proxy' => false,
                'proxy_headers' => null,
            ];
        }
    }

    /**
     * Get the default queue file path based on the operating system
     * 
     * @return string The default queue file path
     */
    public static function getDefaultQueueFilePath(): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $tempDir = getenv('TEMP') ?: getenv('TMP') ?: sys_get_temp_dir();
            return rtrim($tempDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sockeon.queue';
        } else {
            return '/tmp/sockeon.queue';
        }
    }

    /**
     * Set a configuration value
     * 
     * @param string $key The configuration key
     * @param string $value The configuration value
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        self::init();
        self::$config[$key] = $value;
    }

    /**
     * Get a configuration value
     * 
     * @param string $key The configuration key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The configuration value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::init();
        return self::$config[$key] ?? $default;
    }

    /**
     * Get the queue file path
     * 
     * @return string The queue file path
     */
    public static function getQueueFile(): string
    {
        self::init();
        $queueFile = self::$config['queue_file'];
        return is_string($queueFile) ? $queueFile : self::getDefaultQueueFilePath();
    }

    /**
     * Set the queue file path
     * 
     * @param string $path The queue file path
     * @return void
     */
    public static function setQueueFile(string $path): void
    {
        self::init();
        self::$config['queue_file'] = $path;
    }

    /**
     * Get all configuration values
     * 
     * @return array<string, mixed> All configuration values
     */
    public static function getAll(): array
    {
        self::init();
        return self::$config;
    }

    /**
     * Set the authentication key for WebSocket connections
     * 
     * @param string|null $key The authentication key (null to disable authentication)
     * @return void
     */
    public static function setAuthKey(?string $key): void
    {
        self::init();
        self::$config['auth_key'] = $key;
    }

    /**
     * Get the authentication key for WebSocket connections
     * 
     * @return string|null The authentication key (null if authentication is disabled)
     */
    public static function getAuthKey(): ?string
    {
        self::init();
        $authKey = self::$config['auth_key'] ?? null;
        return is_string($authKey) ? $authKey : null;
    }

    /**
     * Reset configuration to defaults
     * 
     * @return void
     */
    public static function reset(): void
    {
        self::$config = [
            'queue_file' => self::getDefaultQueueFilePath(),
            'auth_key' => null,
            'trust_proxy' => false,
            'proxy_headers' => null,
        ];
    }

    /**
     * Set trust proxy settings
     * 
     * @param bool|array<int, string> $trustProxy Trust proxy settings
     * @return void
     */
    public static function setTrustProxy(bool|array $trustProxy): void
    {
        self::init();
        self::$config['trust_proxy'] = $trustProxy;
    }

    /**
     * Get trust proxy settings
     * 
     * @return bool|array<int, string> Trust proxy settings
     */
    public static function getTrustProxy(): bool|array
    {
        self::init();
        $trustProxy = self::$config['trust_proxy'] ?? false;
        if (is_bool($trustProxy)) {
            return $trustProxy;
        }
        if (is_array($trustProxy)) {
            /** @var array<int, string> $trustProxy */
            return $trustProxy;
        }
        return false;
    }

    /**
     * Set custom proxy headers
     * 
     * @param array<string, string>|null $proxyHeaders Custom proxy headers
     * @return void
     */
    public static function setProxyHeaders(?array $proxyHeaders): void
    {
        self::init();
        self::$config['proxy_headers'] = $proxyHeaders;
    }

    /**
     * Get custom proxy headers
     * 
     * @return array<string, string>|null Custom proxy headers
     */
    public static function getProxyHeaders(): ?array
    {
        self::init();
        $proxyHeaders = self::$config['proxy_headers'] ?? null;
        if ($proxyHeaders === null) {
            return null;
        }
        if (is_array($proxyHeaders)) {
            /** @var array<string, string> $proxyHeaders */
            return $proxyHeaders;
        }
        return null;
    }

    /**
     * Load configuration from an array
     * 
     * @param array<string, string> $config Configuration array
     * @return void
     */
    public static function load(array $config): void
    {
        self::init();
        self::$config = array_merge(self::$config, $config);
    }
}
