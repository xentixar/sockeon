<?php
/**
 * HttpHandler class
 * 
 * Handles HTTP protocol implementation, request parsing and responses
 * 
 * Features:
 * - HTTP request parsing
 * - Query parameter extraction
 * - Path normalization
 * - JSON body parsing
 * - Response generation
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http;

use Sockeon\Sockeon\Core\Server;
use Sockeon\Sockeon\Http\Request;
use Sockeon\Sockeon\Http\Response;

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
        $requestData = $this->parseHttpRequest($data);
        $request = new Request($requestData);
        
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
        
        // Parse the URL to extract query parameters and path
        $query = [];
        $url = parse_url($path);
        $originalPath = $path;
        
        // Extract path from URL
        if (isset($url['path'])) {
            $path = $url['path'];
            
            // Normalize path
            if (empty($path)) {
                $path = '/';
            } elseif ($path[0] !== '/') {
                $path = '/' . $path;
            }
        } else {
            $path = '/';
        }
        
        // Extract query parameters
        if (isset($url['query'])) {
            parse_str($url['query'], $query);
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
     * @param Request $request  The Request object
     * @return string           The HTTP response string
     */
    protected function processRequest(Request $request): string
    {
        $path = $request->getPath();
        $method = $request->getMethod();
        
        // Use router to dispatch the request through middleware
        $result = $this->server->getRouter()->dispatchHttp($request);
        
        // Convert controller result to Response object if needed
        if ($result instanceof Response) {
            $response = $result;
        } elseif ($result !== null) {
            if (is_array($result) || is_object($result)) {
                $response = Response::json($result);
            } else {
                $response = new Response($result);
            }
        } else {
            $response = Response::notFound();
        }
        
        return $response->toString();
    }


}
