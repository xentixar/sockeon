<?php
/**
 * HttpHandler class
 * 
 * Handles HTTP protocol implementation, request parsing and responses
 * 
 * @package     Xentixar\Socklet
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Xentixar\Socklet\Http;

use Xentixar\Socklet\Core\Server;

class HttpHandler
{
    /**
     * Reference to the server instance
     * @var Server
     */
    protected $server;
    
    /**
     * Registered HTTP routes
     * @var array
     */
    protected array $routes = [];

    /**
     * Constructor
     * 
     * @param Server $server  The server instance
     */
    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    /**
     * Handle an incoming HTTP request
     * 
     * @param int       $clientId  The client identifier
     * @param resource  $client    The client socket resource
     * @param string    $data      The raw HTTP request data
     * @return void
     */
    public function handle(int $clientId, $client, string $data): void
    {
        $request = $this->parseHttpRequest($data);
        
        // Create response
        $response = $this->processRequest($request);
        
        // Send response
        fwrite($client, $response);
    }

    /**
     * Parse raw HTTP request into structured format
     * 
     * @param string $data  The raw HTTP request data
     * @return array        The parsed request as an associative array
     */
    protected function parseHttpRequest(string $data): array
    {
        $lines = explode("\r\n", $data);
        $requestLine = explode(' ', array_shift($lines));
        
        $method = $requestLine[0] ?? '';
        $path = $requestLine[1] ?? '/';
        $protocol = $requestLine[2] ?? '';
        
        $headers = [];
        $headersDone = false;
        $body = '';
        
        foreach ($lines as $line) {
            if (!$headersDone) {
                if (empty(trim($line))) {
                    $headersDone = true;
                    continue;
                }
                
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $headers[trim($key)] = trim($value);
                }
            } else {
                $body .= $line;
            }
        }
        
        $query = [];
        $url = parse_url($path);
        if (isset($url['query'])) {
            parse_str($url['query'], $query);
            $path = $url['path'];
        }

        // Try to parse JSON body
        $parsedBody = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $body = $parsedBody;
        }
        
        return [
            'method' => $method,
            'path' => $path,
            'protocol' => $protocol,
            'headers' => $headers,
            'query' => $query,
            'body' => $body
        ];
    }

    /**
     * Process an HTTP request and generate response
     * 
     * @param array $request  The parsed HTTP request
     * @return string         The HTTP response string
     */
    protected function processRequest(array $request): string
    {
        $path = $request['path'];
        $method = $request['method'];
        
        // Use router to dispatch the request through middleware
        $response = $this->server->getRouter()->dispatchHttp($request);
        
        if ($response !== null) {
            if (is_array($response)) {
                $body = json_encode($response);
                $contentType = 'application/json';
                $statusCode = 200;
            } else {
                $body = $response;
                $contentType = 'text/html';
                $statusCode = 200;
            }
        } else {
            $body = json_encode(['error' => 'Not Found']);
            $contentType = 'application/json';
            $statusCode = 404;
        }
        
        $headers = [
            "HTTP/1.1 {$statusCode} " . $this->getStatusText($statusCode),
            "Content-Type: {$contentType}",
            "Connection: close",
            "Content-Length: " . strlen($body)
        ];
        
        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /**
     * Get HTTP status message from status code
     * 
     * Returns the standardized HTTP status text for a given status code.
     * If code is not recognized, returns "Unknown".
     * 
     * @param int $code   HTTP status code (e.g., 200, 404, 500)
     * @return string     Corresponding status text
     */
    protected function getStatusText(int $code): string
    {
        $texts = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error'
        ];
        
        return $texts[$code] ?? 'Unknown';
    }
}
