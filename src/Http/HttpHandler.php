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
     * CORS configuration
     * @var CorsConfig
     */
    protected CorsConfig $corsConfig;

    /**
     * Constructor
     * 
     * @param Server $server  The server instance
     * @param array $corsConfig Optional CORS configuration
     */
    public function __construct(Server $server, array $corsConfig = [])
    {
        $this->server = $server;
        $this->corsConfig = new CorsConfig($corsConfig);
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
        $this->debug("Received HTTP request from client #{$clientId}");
        
        $requestData = $this->parseHttpRequest($data);
        $request = new Request($requestData);
        
        $this->debug("Parsed request", [
            'method' => $request->getMethod(),
            'path' => $request->getPath(),
            'headers' => $request->getHeaders()
        ]);
        
        if ($request->getMethod() === 'OPTIONS') {
            $this->debug("Handling preflight OPTIONS request");
            $response = $this->handleCorsPreflightRequest($request);
        } else {
            $this->debug("Processing standard request");
            $response = $this->processRequest($request);
            
            $this->debug("Applying CORS headers");
            $response = $this->applyCorsHeaders($request, $response);
        }
        
        $this->debug("Sending response", [
            'size' => strlen($response),
            'preview' => substr($response, 0, 100) . (strlen($response) > 100 ? '...' : '')
        ]);
        
        fwrite($client, $response);
    }
    
    /**
     * Handle CORS preflight request
     * 
     * @param Request $request
     * @return string
     */
    protected function handleCorsPreflightRequest(Request $request): string
    {
        $response = new Response('', 204);
        $response->setHeader('Content-Type', 'text/plain');
        return $this->applyCorsHeaders($request, $response->toString(), true);
    }
    
    /**
     * Apply CORS headers to a response
     * 
     * @param Request $request The request object
     * @param string|Response $response The response or response string
     * @param bool $isPreflight Whether this is a preflight request
     * @return string The response with CORS headers
     */
    protected function applyCorsHeaders(Request $request, $response, bool $isPreflight = false): string
    {
        if ($response instanceof Response) {
            $response = $response->toString();
        }
        
        $origin = $request->getHeader('Origin');
        
        if (!$origin) {
            return $response;
        }
        
        if (!$this->corsConfig->isOriginAllowed($origin)) {
            return $response;
        }
        
        [$headers, $body] = $this->parseHttpResponse($response);
        
        $headers['Access-Control-Allow-Origin'] = $origin;
        
        if ($this->corsConfig->isCredentialsAllowed()) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }
        
        if ($isPreflight) {
            $allowedMethods = $this->corsConfig->getAllowedMethods();
            $headers['Access-Control-Allow-Methods'] = is_array($allowedMethods) 
                ? implode(', ', $allowedMethods) 
                : $allowedMethods;
            
            $allowedHeaders = $this->corsConfig->getAllowedHeaders();
            $headers['Access-Control-Allow-Headers'] = is_array($allowedHeaders) 
                ? implode(', ', $allowedHeaders) 
                : $allowedHeaders;
            
            $headers['Access-Control-Max-Age'] = $this->corsConfig->getMaxAge();
        }
        
        $headerString = '';
        if (isset($headers['Status-Line'])) {
            $headerString .= $headers['Status-Line'] . "\r\n";
            unset($headers['Status-Line']);
        }
        
        foreach ($headers as $name => $value) {
            $headerString .= "$name: $value\r\n";
        }
        
        return $headerString . "\r\n" . $body;
    }
    
    /**
     * Parse HTTP response into headers and body
     * 
     * @param string $response The HTTP response string
     * @return array [headers, body]
     */
    protected function parseHttpResponse(string $response): array
    {
        $parts = explode("\r\n\r\n", $response, 2);
        
        $headerLines = explode("\r\n", $parts[0]);
        $statusLine = array_shift($headerLines);
        
        $headers = ['Status-Line' => $statusLine];
        foreach ($headerLines as $line) {
            if (strpos($line, ':') !== false) {
                list($name, $value) = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }
        
        $body = $parts[1] ?? '';
        
        return [$headers, $body];
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
        
        if (isset($url['path'])) {
            $path = $url['path'];
            
            if (empty($path)) {
                $path = '/';
            } elseif ($path[0] !== '/') {
                $path = '/' . $path;
            }
        } else {
            $path = '/';
        }
        
        if (isset($url['query'])) {
            parse_str($url['query'], $query);
        }

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

    /**
     * Log debug information if debug mode is enabled
     * 
     * @param string $message The debug message
     * @param mixed $data Additional data to log
     * @return void
     */
    protected function debug(string $message, $data = null): void
    {
        $dataString = $data !== null ? ' ' . json_encode($data) : '';
        $this->server->log("[Sockeon HTTP Debug] {$message}{$dataString}");
    }
}
