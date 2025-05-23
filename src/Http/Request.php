<?php
/**
 * Request class
 * 
 * Handles HTTP request data encapsulation and provides convenient
 * methods to access request parameters, headers, and body
 * 
 * @package     Xentixar\Socklet
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Xentixar\Socklet\Http;

class Request
{
    /**
     * HTTP method (GET, POST, PUT, etc.)
     * @var string
     */
    protected string $method;
    
    /**
     * Request path
     * @var string
     */
    protected string $path;
    
    /**
     * HTTP protocol version
     * @var string
     */
    protected string $protocol;
    
    /**
     * Request headers
     * @var array
     */
    protected array $headers;
    
    /**
     * Query parameters
     * @var array
     */
    protected array $query;
    
    /**
     * Path parameters
     * @var array
     */
    protected array $params;
    
    /**
     * Request body
     * @var mixed
     */
    protected mixed $body;
    
    /**
     * Raw request data
     * @var array
     */
    protected array $rawData;
    
    /**
     * Normalized headers cache (lowercase keys)
     * @var array|null
     */
    protected ?array $normalizedHeaders = null;

    /**
     * Constructor
     * 
     * @param array $requestData The parsed HTTP request data
     */
    public function __construct(array $requestData)
    {
        $this->method = $requestData['method'] ?? '';
        $this->path = $requestData['path'] ?? '/';
        $this->protocol = $requestData['protocol'] ?? '';
        $this->headers = $requestData['headers'] ?? [];
        $this->query = $requestData['query'] ?? [];
        $this->params = $requestData['params'] ?? [];
        $this->rawData = $requestData;

        // Handle JSON body
        $body = $requestData['body'] ?? null;
        if (is_string($body) && $this->isJson()) {
            $decodedBody = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->body = $decodedBody;
                return;
            }
        }
        $this->body = $body;
    }

    /**
     * Get HTTP method
     * 
     * @return string The HTTP method
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get request path
     * 
     * @return string The request path
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get HTTP protocol
     * 
     * @return string The HTTP protocol version
     */
    public function getProtocol(): string
    {
        return $this->protocol;
    }

    /**
     * Get all headers
     * 
     * @return array All request headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header value
     * 
     * @param string $name The header name (case-insensitive)
     * @param mixed $default Default value if header is not found
     * @return mixed The header value or default
     */
    public function getHeader(string $name, mixed $default = null): mixed
    {
        // Initialize normalized headers cache if not already done
        if ($this->normalizedHeaders === null) {
            $this->normalizedHeaders = [];
            foreach ($this->headers as $key => $value) {
                $this->normalizedHeaders[strtolower($key)] = $value;
            }
        }
        
        $name = strtolower($name);
        return $this->normalizedHeaders[$name] ?? $default;
    }

    /**
     * Get all query parameters
     * 
     * @return array All query parameters
     */
    public function getQueryParams(): array
    {
        return $this->query;
    }

    /**
     * Get a specific query parameter
     * 
     * @param string $name The query parameter name
     * @param mixed $default Default value if parameter is not found
     * @return mixed The parameter value or default
     */
    public function getQuery(string $name, mixed $default = null): mixed
    {
        return $this->query[$name] ?? $default;
    }

    /**
     * Get all path parameters
     * 
     * @return array All path parameters
     */
    public function getPathParams(): array
    {
        return $this->params;
    }

    /**
     * Get a specific path parameter
     * 
     * @param string $name The path parameter name
     * @param mixed $default Default value if parameter is not found
     * @return mixed The parameter value or default
     */
    public function getParam(string $name, mixed $default = null): mixed
    {
        return $this->params[$name] ?? $default;
    }

    /**
     * Get request body
     * 
     * @return mixed The request body
     */
    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * Check if the request has a JSON content type
     * 
     * @return bool True if request has JSON content type
     */
    public function isJson(): bool
    {
        $contentType = $this->getHeader('Content-Type');
        return $contentType && strpos($contentType, 'application/json') !== false;
    }
    
    /**
     * Check if the request is an XHR/AJAX request
     * 
     * @return bool True if the request is an AJAX request
     */
    public function isAjax(): bool
    {
        return $this->getHeader('X-Requested-With') === 'XMLHttpRequest';
    }
    
    /**
     * Check if this is a specific HTTP method
     * 
     * @param string $method The HTTP method to check (case-insensitive)
     * @return bool True if the request method matches
     */
    public function isMethod(string $method): bool
    {
        return strtoupper($this->method) === strtoupper($method);
    }
    
    /**
     * Get the request URL
     * 
     * @param bool $includeQuery Whether to include the query string
     * @return string The full request URL
     */
    public function getUrl(bool $includeQuery = true): string
    {
        $host = $this->getHeader('Host', '');
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $url = "{$protocol}://{$host}{$this->path}";
        
        if ($includeQuery && !empty($this->query)) {
            $url .= '?' . http_build_query($this->query);
        }
        
        return $url;
    }
    
    /**
     * Get client IP address
     * 
     * @return string|null The client IP address or null
     */
    public function getIpAddress(): ?string
    {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (isset($_SERVER[$header])) {
                $ip = filter_var($_SERVER[$header], FILTER_VALIDATE_IP);
                if ($ip !== false) {
                    return $ip;
                }
            }
        }
        
        return null;
    }

    /**
     * Create from raw request array
     * 
     * @param array $request The raw request array
     * @return self New Request instance
     */
    public static function fromArray(array $request): self
    {
        return new self($request);
    }

    /**
     * Convert to array
     * 
     * @return array The request as an array
     */
    public function toArray(): array
    {
        return $this->rawData;
    }
    
    /**
     * Set custom data in the request
     * 
     * @param string $key The data key
     * @param mixed $value The data value
     * @return self For method chaining
     */
    public function setData(string $key, mixed $value): self
    {
        $this->rawData[$key] = $value;
        return $this;
    }
    
    /**
     * Get custom data from the request
     * 
     * @param string $key The data key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The data value or default
     */
    public function getData(string $key, mixed $default = null): mixed
    {
        return $this->rawData[$key] ?? $default;
    }
}
