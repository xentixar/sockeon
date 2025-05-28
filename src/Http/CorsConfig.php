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
     * @var array|string
     */
    protected $allowedOrigins = ['*'];

    /**
     * Allowed methods
     * @var array|string
     */
    protected $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH', 'HEAD'];

    /**
     * Allowed headers
     * @var array|string
     */
    protected $allowedHeaders = ['Content-Type', 'X-Requested-With', 'Authorization'];

    /**
     * Whether to allow credentials
     * @var bool
     */
    protected $allowCredentials = false;

    /**
     * Max age for preflight requests
     * @var int
     */
    protected $maxAge = 86400;

    /**
     * Create a new CORS configuration instance
     */
    public function __construct(array $config = [])
    {
        if (isset($config['origins'])) {
            $this->allowedOrigins = $config['origins'];
        }
        
        if (isset($config['methods'])) {
            $this->allowedMethods = $config['methods'];
        }
        
        if (isset($config['headers'])) {
            $this->allowedHeaders = $config['headers'];
        }
        
        if (isset($config['credentials'])) {
            $this->allowCredentials = $config['credentials'];
        }
        
        if (isset($config['max_age'])) {
            $this->maxAge = $config['max_age'];
        }
    }

    /**
     * Get allowed origins
     * 
     * @return array|string
     */
    public function getAllowedOrigins()
    {
        return $this->allowedOrigins;
    }

    /**
     * Get allowed methods
     * 
     * @return array|string
     */
    public function getAllowedMethods()
    {
        return $this->allowedMethods;
    }

    /**
     * Get allowed headers
     * 
     * @return array|string
     */
    public function getAllowedHeaders()
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
