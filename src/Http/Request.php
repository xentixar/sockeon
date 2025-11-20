<?php
/**
 * Request class
 * 
 * Handles HTTP request data encapsulation and provides convenient
 * methods to access request parameters, headers, and body
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http;

use Sockeon\Sockeon\Core\Config;
use Sockeon\Sockeon\Validation\Validator;
use Sockeon\Sockeon\Exception\Validation\ValidationException;

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
     * @var array<string, string>
     */
    protected array $headers;
    
    /**
     * Query parameters
     * @var array<string, mixed>
     */
    protected array $query;
    
    /**
     * Path parameters
     * @var array<string, string>
     */
    protected array $params;
    
    /**
     * Request body
     * @var mixed
     */
    protected mixed $body;
    
    /**
     * Raw request data
     * @var array<string, mixed>
     */
    protected array $rawData;
    
    /**
     * Normalized headers cache (lowercase keys)
     * @var array<string, string>|null
     */
    protected ?array $normalizedHeaders = null;

    /**
     * Validator instance
     * @var Validator|null
     */
    protected ?Validator $validator = null;

    /**
     * Validation rules
     * @var array<string, string|array<int, string>>
     */
    protected array $validationRules = [];

    /**
     * Custom error messages
     * @var array<string, string>
     */
    protected array $validationMessages = [];

    /**
     * Custom field names
     * @var array<string, string>
     */
    protected array $validationFieldNames = [];

    /**
     * Constructor
     * 
     * @param array<string, mixed> $requestData The parsed HTTP request data
     */
    public function __construct(array $requestData)
    {
        $this->method = isset($requestData['method']) ?
            (is_string($requestData['method']) ? $requestData['method'] : '') : '';

        $this->path = isset($requestData['path']) ?
            (is_string($requestData['path']) ? $requestData['path'] : '/') : '/';

        $this->protocol = isset($requestData['protocol']) ?
            (is_string($requestData['protocol']) ? $requestData['protocol'] : '') : '';

        $this->headers = [];
        if (isset($requestData['headers']) && is_array($requestData['headers'])) {
            foreach ($requestData['headers'] as $key => $value) {
                $headerKey = is_string($key) ? $key : '';
                $headerValue = is_string($value) ? $value : '';
                if (!empty($headerKey)) {
                    $this->headers[$headerKey] = $headerValue;
                }
            }
        }

        $this->query = [];
        if (isset($requestData['query']) && is_array($requestData['query'])) {
            foreach ($requestData['query'] as $key => $value) {
                $queryKey = is_string($key) ? $key : '';
                if (!empty($queryKey)) {
                    $this->query[$queryKey] = $value;
                }
            }
        }

        $this->params = [];
        if (isset($requestData['params']) && is_array($requestData['params'])) {
            foreach ($requestData['params'] as $key => $value) {
                $paramKey = is_string($key) ? $key : '';
                $paramValue = is_string($value) ? $value : '';
                if (!empty($paramKey)) {
                    $this->params[$paramKey] = $paramValue;
                }
            }
        }

        $this->rawData = $requestData;

        $body = $requestData['body'] ?? null;
        if (is_string($body) && $this->isJson()) {
            $decodedBody = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->body = $decodedBody;
                return;
            }
        } elseif (is_string($body) && $this->isFormData()) {
            $formData = [];
            parse_str($body, $formData);
            $this->body = $formData;
            return;
        }
        $this->body = $body;
    }

    /**
     * Check if the request has form data content type
     * 
     * @return bool True if request has form data content type
     */
    public function isFormData(): bool
    {
        $contentType = $this->getHeader('Content-Type');
        if (!is_string($contentType)) {
            return false;
        }
        return str_contains($contentType, 'application/x-www-form-urlencoded');
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
     * @return array<string, string> All request headers
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
     * @return array<string, mixed> All query parameters
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
     * @return array<string, string> All path parameters
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
     * Get request body as array (similar to Laravel's all() method)
     * 
     * @return array<mixed, mixed> The request body as an array
     */
    public function all(): array
    {
        if (is_array($this->body)) {
            return $this->body;
        }
        
        if (is_string($this->body) && !empty($this->body)) {
            $decoded = json_decode($this->body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        
        return [];
    }

    /**
     * Check if the request has a JSON content type
     * 
     * @return bool True if request has JSON content type
     */
    public function isJson(): bool
    {
        $contentType = $this->getHeader('Content-Type');
        if (!is_string($contentType)) {
            return false;
        }
        return str_contains($contentType, 'application/json');
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
        $host = $this->getHost();
        $protocol = $this->getScheme();
        $port = $this->getPort();
        
        $url = "{$protocol}://{$host}";
        
        // Only include port if it's not the default port for the scheme
        if (($protocol === 'https' && $port !== 443) || ($protocol === 'http' && $port !== 80)) {
            $url .= ":{$port}";
        }
        
        $url .= $this->path;
        
        if ($includeQuery && !empty($this->query)) {
            $url .= '?' . http_build_query($this->query);
        }
        
        return $url;
    }
    
    /**
     * Get client IP address
     * 
     * @param bool $fallbackToDefault Whether to return a default IP if none found
     * @return string|null The client IP address or null/default
     */
    public function getIpAddress(bool $fallbackToDefault = false): ?string
    {
        // Check if we should trust proxy headers
        if (!$this->shouldTrustProxy()) {
            // Don't trust proxies, use direct connection IP
            $directIp = $this->getDirectConnectionIp();
            if ($directIp !== null) {
                return $directIp;
            }
            return $fallbackToDefault ? '127.0.0.1' : null;
        }

        // Trust proxy headers - check in order of preference
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy (RFC 7239)
        ];

        foreach ($ipHeaders as $header) {
            $ip = $this->getHeader($header);
            if (is_string($ip) && $ip !== '' && $ip !== 'unknown') {
                // Handle comma-separated IPs (take the first one, which is the original client)
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Prefer public IPs
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
                
                // Fallback to any valid IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        // Fallback to direct connection IP
        $directIp = $this->getDirectConnectionIp();
        if ($directIp !== null) {
            return $directIp;
        }
        
        return $fallbackToDefault ? '127.0.0.1' : null;
    }

    /**
     * Get the direct connection IP (without proxy headers)
     * 
     * @return string|null The direct connection IP or null
     */
    protected function getDirectConnectionIp(): ?string
    {
        // Try REMOTE_ADDR from headers first
        $remoteAddr = $this->getHeader('REMOTE_ADDR');
        if (is_string($remoteAddr) && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            return $remoteAddr;
        }

        // Fallback to $_SERVER
        if (isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return null;
    }

    /**
     * Check if we should trust proxy headers
     * 
     * @return bool True if proxy headers should be trusted
     */
    protected function shouldTrustProxy(): bool
    {
        $trustProxy = Config::getTrustProxy();
        
        if ($trustProxy === false) {
            return false;
        }
        
        if ($trustProxy === true) {
            return true;
        }
        
        // Check if the direct connection IP is in the trusted proxy list
        // At this point, $trustProxy must be an array since we've checked for false and true
        /** @var array<int, string> $trustProxy */
        $directIp = $this->getDirectConnectionIp();
        if ($directIp === null) {
            return false;
        }
        
        foreach ($trustProxy as $trustedProxy) {
            if ($this->ipMatches($directIp, $trustedProxy)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if an IP matches a trusted proxy pattern (IP or CIDR)
     * 
     * @param string $ip The IP address to check
     * @param string $pattern The pattern (IP or CIDR notation)
     * @return bool True if the IP matches
     */
    protected function ipMatches(string $ip, string $pattern): bool
    {
        // Exact match
        if ($ip === $pattern) {
            return true;
        }
        
        // CIDR notation match
        if (str_contains($pattern, '/')) {
            list($subnet, $mask) = explode('/', $pattern, 2);
            $mask = (int) $mask;
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ipLong = ip2long($ip);
                $subnetLong = ip2long($subnet);
                if ($ipLong === false || $subnetLong === false) {
                    return false;
                }
                $maskLong = -1 << (32 - $mask);
                return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
            }
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                // IPv6 CIDR matching (simplified)
                $ipBin = inet_pton($ip);
                $subnetBin = inet_pton($subnet);
                if ($ipBin === false || $subnetBin === false) {
                    return false;
                }
                $bytes = (int) ($mask / 8);
                $bits = $mask % 8;
                return substr($ipBin, 0, $bytes) === substr($subnetBin, 0, $bytes) &&
                       ($bits === 0 || (ord($ipBin[$bytes]) >> (8 - $bits)) === (ord($subnetBin[$bytes]) >> (8 - $bits)));
            }
        }
        
        return false;
    }

    /**
     * Get the request scheme (http or https)
     * Respects X-Forwarded-Proto header when proxy is trusted
     * 
     * @return string The request scheme
     */
    public function getScheme(): string
    {
        if ($this->shouldTrustProxy()) {
            $proxyHeaders = Config::getProxyHeaders();
            $protoHeader = $proxyHeaders['proto'] ?? 'X-Forwarded-Proto';
            
            $proto = $this->getHeader($protoHeader);
            if (is_string($proto) && in_array(strtolower($proto), ['http', 'https'], true)) {
                return strtolower($proto);
            }
            
            // Also check Forwarded header (RFC 7239)
            $forwarded = $this->getHeader('Forwarded');
            if (is_string($forwarded)) {
                if (preg_match('/proto=([^;,\s]+)/i', $forwarded, $matches)) {
                    $proto = strtolower(trim($matches[1], '"'));
                    if (in_array($proto, ['http', 'https'], true)) {
                        return $proto;
                    }
                }
            }
        }
        
        // Fallback to checking connection or default to http
        return 'http';
    }

    /**
     * Get the request host
     * Respects X-Forwarded-Host header when proxy is trusted
     * 
     * @return string The request host
     */
    public function getHost(): string
    {
        if ($this->shouldTrustProxy()) {
            $proxyHeaders = Config::getProxyHeaders();
            $hostHeader = $proxyHeaders['host'] ?? 'X-Forwarded-Host';
            
            $host = $this->getHeader($hostHeader);
            if (is_string($host) && $host !== '') {
                // Remove port if present (we'll handle it separately)
                if (str_contains($host, ':')) {
                    $host = explode(':', $host)[0];
                }
                return $host;
            }
            
            // Also check Forwarded header (RFC 7239)
            $forwarded = $this->getHeader('Forwarded');
            if (is_string($forwarded)) {
                if (preg_match('/host=([^;,\s]+)/i', $forwarded, $matches)) {
                    $host = trim($matches[1], '"');
                    if (str_contains($host, ':')) {
                        $host = explode(':', $host)[0];
                    }
                    return $host;
                }
            }
        }
        
        // Fallback to Host header
        $host = $this->getHeader('Host');
        if (is_string($host) && $host !== '') {
            if (str_contains($host, ':')) {
                $host = explode(':', $host)[0];
            }
            return $host;
        }
        
        return 'localhost';
    }

    /**
     * Get the request port
     * Respects X-Forwarded-Port header when proxy is trusted
     * 
     * @return int The request port
     */
    public function getPort(): int
    {
        if ($this->shouldTrustProxy()) {
            $proxyHeaders = Config::getProxyHeaders();
            $portHeader = $proxyHeaders['port'] ?? 'X-Forwarded-Port';
            
            $port = $this->getHeader($portHeader);
            if (is_string($port) && is_numeric($port)) {
                $portInt = (int) $port;
                if ($portInt > 0 && $portInt <= 65535) {
                    return $portInt;
                }
            }
            
            // Also check Forwarded header (RFC 7239)
            $forwarded = $this->getHeader('Forwarded');
            if (is_string($forwarded)) {
                if (preg_match('/host=([^;,\s]+)/i', $forwarded, $matches)) {
                    $host = trim($matches[1], '"');
                    if (str_contains($host, ':')) {
                        $parts = explode(':', $host);
                        $port = end($parts);
                        if (is_numeric($port)) {
                            $portInt = (int) $port;
                            if ($portInt > 0 && $portInt <= 65535) {
                                return $portInt;
                            }
                        }
                    }
                }
            }
        }
        
        // Fallback to Host header port
        $host = $this->getHeader('Host');
        if (is_string($host) && str_contains($host, ':')) {
            $parts = explode(':', $host);
            $port = end($parts);
            if (is_numeric($port)) {
                $portInt = (int) $port;
                if ($portInt > 0 && $portInt <= 65535) {
                    return $portInt;
                }
            }
        }
        
        // Default port based on scheme
        return $this->getScheme() === 'https' ? 443 : 80;
    }

    /**
     * Create from raw request array
     * 
     * @param array<string, mixed> $request The raw request array
     * @return self New Request instance
     */
    public static function fromArray(array $request): self
    {
        return new self($request);
    }

    /**
     * Convert to array
     * 
     * @return array<string, mixed> The request as an array
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
    public function setAttribute(string $key, mixed $value): self
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
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->rawData[$key] ?? $default;
    }

    /**
     * Magic method to access request data as properties
     * Checks body data first, then query parameters
     * 
     * @param string $name The property name
     * @return mixed The property value or null
     */
    public function __get(string $name): mixed
    {
        $bodyData = $this->all();
        if (array_key_exists($name, $bodyData)) {
            return $bodyData[$name];
        }
        
        if (array_key_exists($name, $this->query)) {
            return $this->query[$name];
        }
        
        if (array_key_exists($name, $this->params)) {
            return $this->params[$name];
        }
        
        return null;
    }

    /**
     * Magic method to check if a property exists
     * 
     * @param string $name The property name
     * @return bool True if the property exists
     */
    public function __isset(string $name): bool
    {
        $bodyData = $this->all();
        return array_key_exists($name, $bodyData) || 
               array_key_exists($name, $this->query) || 
               array_key_exists($name, $this->params);
    }

    /**
     * Get a specific input value (body, query, or path parameter)
     * 
     * @param string $key The input key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The input value or default
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->__get($key) ?? $default;
    }

    /**
     * Check if a specific input exists
     * 
     * @param string $key The input key
     * @return bool True if the input exists
     */
    public function has(string $key): bool
    {
        return $this->__isset($key);
    }

    /**
     * Validate the request data
     * 
     * @param array<string, string|array<int, string>> $rules The validation rules
     * @param array<string, string> $messages Custom error messages
     * @param array<string, string> $fieldNames Custom field names
     * @return bool True if validation passes
     * @throws ValidationException
     */
    public function validate(array $rules, array $messages = [], array $fieldNames = []): bool
    {
        $this->validationRules = $rules;
        $this->validationMessages = $messages;
        $this->validationFieldNames = $fieldNames;

        if ($this->validator === null) {
            $this->validator = new Validator();
        }

        $data = $this->all();
        $data = array_merge($data, $this->query, $this->params);

        return $this->validator->validate($data, $rules, $messages, $fieldNames);
    }

    /**
     * Validate the request data and return validated data
     * 
     * @param array<string, string|array<int, string>> $rules The validation rules
     * @param array<string, string> $messages Custom error messages
     * @param array<string, string> $fieldNames Custom field names
     * @return array<string, mixed> The validated and sanitized data
     * @throws ValidationException
     */
    public function validated(array $rules, array $messages = [], array $fieldNames = []): array
    {
        $this->validate($rules, $messages, $fieldNames);
        return $this->validator?->getSanitized() ?? [];
    }

    /**
     * Get validation errors
     * 
     * @return array<string, array<int, string>> The validation errors
     */
    public function getValidationErrors(): array
    {
        return $this->validator?->getErrors() ?? [];
    }

    /**
     * Check if validation has errors
     * 
     * @return bool True if there are errors
     */
    public function hasValidationErrors(): bool
    {
        return $this->validator?->hasErrors() ?? false;
    }

    /**
     * Get first validation error for a field
     * 
     * @param string $field The field name
     * @return string|null The first error message or null
     */
    public function getFirstValidationError(string $field): ?string
    {
        return $this->validator?->getFirstError($field);
    }

    /**
     * Get all validation errors for a field
     * 
     * @param string $field The field name
     * @return array<int, string> The error messages
     */
    public function getFieldValidationErrors(string $field): array
    {
        return $this->validator?->getFieldErrors($field) ?? [];
    }

    /**
     * Get validated data
     * 
     * @return array<string, mixed> The validated and sanitized data
     */
    public function getValidatedData(): array
    {
        return $this->validator?->getSanitized() ?? [];
    }

    /**
     * Set validation rules
     * 
     * @param array<string, string|array<int, string>> $rules The validation rules
     * @return self For method chaining
     */
    public function setValidationRules(array $rules): self
    {
        $this->validationRules = $rules;
        return $this;
    }

    /**
     * Set custom error messages
     * 
     * @param array<string, string> $messages The custom error messages
     * @return self For method chaining
     */
    public function setValidationMessages(array $messages): self
    {
        $this->validationMessages = $messages;
        return $this;
    }

    /**
     * Set custom field names
     * 
     * @param array<string, string> $fieldNames The custom field names
     * @return self For method chaining
     */
    public function setValidationFieldNames(array $fieldNames): self
    {
        $this->validationFieldNames = $fieldNames;
        return $this;
    }

    /**
     * Get validation rules
     * 
     * @return array<string, string|array<int, string>> The validation rules
     */
    public function getValidationRules(): array
    {
        return $this->validationRules;
    }

    /**
     * Get custom error messages
     * 
     * @return array<string, string> The custom error messages
     */
    public function getValidationMessages(): array
    {
        return $this->validationMessages;
    }

    /**
     * Get custom field names
     * 
     * @return array<string, string> The custom field names
     */
    public function getValidationFieldNames(): array
    {
        return $this->validationFieldNames;
    }

    /**
     * Validate a single field
     * 
     * @param string $field The field name
     * @param string|array<int, string> $rules The validation rules
     * @param array<string, string> $messages Custom error messages
     * @param array<string, string> $fieldNames Custom field names
     * @return bool True if validation passes
     * @throws ValidationException
     */
    public function validateField(string $field, string|array $rules, array $messages = [], array $fieldNames = []): bool
    {
        if ($this->validator === null) {
            $this->validator = new Validator();
        }

        $data = [$field => $this->input($field)];
        $validationRules = [$field => $rules];

        return $this->validator->validate($data, $validationRules, $messages, $fieldNames);
    }

    /**
     * Validate multiple fields
     * 
     * @param array<string, string|array<int, string>> $rules The validation rules
     * @param array<string, string> $messages Custom error messages
     * @param array<string, string> $fieldNames Custom field names
     * @return bool True if validation passes
     * @throws ValidationException
     */
    public function validateFields(array $rules, array $messages = [], array $fieldNames = []): bool
    {
        if ($this->validator === null) {
            $this->validator = new Validator();
        }

        $data = [];
        foreach (array_keys($rules) as $field) {
            $data[$field] = $this->input($field);
        }

        return $this->validator->validate($data, $rules, $messages, $fieldNames);
    }
}
