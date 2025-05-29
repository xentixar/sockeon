<?php
/**
 * CORS Configuration Class
 * 
 * Handles Cross-Origin Resource Sharing configurations
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http;

class CorsConfig
{
    /**
     * Allowed origins
     * @var array<int, string>
     */
    protected array $allowedOrigins = ['*'];

    /**
     * Allowed methods
     * @var array<int, string>
     */
    protected array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH', 'HEAD'];

    /**
     * Allowed headers
     * @var array<int, string>
     */
    protected array $allowedHeaders = ['Content-Type', 'X-Requested-With', 'Authorization'];

    /**
     * Whether to allow credentials
     * @var bool
     */
    protected bool $allowCredentials = false;

    /**
     * Max age for preflight requests
     * @var int
     */
    protected int $maxAge = 86400;

    /**
     * Create a new CORS configuration instance
     *
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        if (isset($config['origins']) && is_array($config['origins'])) {
            $origins = [];
            foreach ($config['origins'] as $origin) {
                if (is_string($origin)) {
                    $origins[] = $origin;
                } elseif (is_scalar($origin) || (is_object($origin) && method_exists($origin, '__toString'))) {
                    $origins[] = (string) $origin;
                }
            }
            if (!empty($origins)) {
                $this->allowedOrigins = $origins;
            }
        }
        
        if (isset($config['methods']) && is_array($config['methods'])) {
            $methods = [];
            foreach ($config['methods'] as $method) {
                if (is_string($method)) {
                    $methods[] = $method;
                } elseif (is_scalar($method) || (is_object($method) && method_exists($method, '__toString'))) {
                    $methods[] = (string) $method;
                }
            }
            if (!empty($methods)) {
                $this->allowedMethods = $methods;
            }
        }
        
        if (isset($config['headers']) && is_array($config['headers'])) {
            $headers = [];
            foreach ($config['headers'] as $header) {
                if (is_string($header)) {
                    $headers[] = $header;
                } elseif (is_scalar($header) || (is_object($header) && method_exists($header, '__toString'))) {
                    $headers[] = (string) $header;
                }
            }
            if (!empty($headers)) {
                $this->allowedHeaders = $headers;
            }
        }
        
        if (isset($config['credentials'])) {
            if (is_bool($config['credentials'])) {
                $this->allowCredentials = $config['credentials'];
            } else {
                $this->allowCredentials = !empty($config['credentials']);
            }
        }
        
        if (isset($config['max_age'])) {
            if (is_int($config['max_age'])) {
                $this->maxAge = $config['max_age'];
            } elseif (is_numeric($config['max_age'])) {
                $this->maxAge = (int) $config['max_age'];
            }
        }
    }

    /**
     * Get allowed origins
     * 
     * @return array<int, string>
     */
    public function getAllowedOrigins(): array
    {
        return $this->allowedOrigins;
    }

    /**
     * Get allowed methods
     * 
     * @return array<int, string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }

    /**
     * Get allowed headers
     * 
     * @return array<int, string>
     */
    public function getAllowedHeaders(): array
    {
        return $this->allowedHeaders;
    }

    /**
     * Check if credentials are allowed
     * 
     * @return bool
     */
    public function isCredentialsAllowed(): bool
    {
        return $this->allowCredentials;
    }

    /**
     * Get max age
     * 
     * @return int
     */
    public function getMaxAge(): int
    {
        return $this->maxAge;
    }

    /**
     * Check if the origin is allowed
     * 
     * @param string $origin
     * @return bool
     */
    public function isOriginAllowed(string $origin): bool
    {
        if ($this->allowedOrigins === ['*']) {
            return true;
        }
        
        return in_array($origin, $this->allowedOrigins);
    }
}
