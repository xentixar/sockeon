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
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http;

use Sockeon\Sockeon\Connection\Server;
use Sockeon\Sockeon\Traits\Http\HandlesCors;
use Sockeon\Sockeon\Traits\Http\HandlesHttpLogging;
use Sockeon\Sockeon\Traits\Http\HandlesHttpRequests;
use Throwable;

class Handler
{
    use HandlesCors, HandlesHttpLogging, HandlesHttpRequests;
    /**
     * Reference to the server instance
     * @var Server
     */
    protected Server $server;

    /**
     * Registered HTTP routes
     * @var array<string, array{0: object, 1: string}>
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
     * @param Server $server The server instance
     * @param array<string, mixed> $corsConfig Optional CORS configuration
     */
    public function __construct(Server $server, array $corsConfig = [])
    {
        $this->server = $server;
        $this->corsConfig = new CorsConfig($corsConfig);
    }

    /**
     * Handle an incoming HTTP request
     * 
     * @param int $clientId The client identifier
     * @param resource $client The client socket resource
     * @param string $data The raw HTTP request data
     * @return void
     */
    public function handle(int $clientId, $client, string $data): void
    {
        try {
            $this->debug("Received HTTP request from client #{$clientId}");
            
            $requestData = $this->parseHttpRequest($data);
            $request = new Request($requestData);
            
            if ($request->getMethod() === 'OPTIONS') {
                $this->debug("Handling preflight OPTIONS request");
                $response = $this->handleCorsPreflightRequest($request);
            } else {
                $this->debug("Processing standard request");
                $response = $this->processRequest($request);
                
                $this->debug("Applying CORS headers");
                $response = $this->applyCorsHeaders($request, $response);
            }
            
            fwrite($client, $response);
        } catch (Throwable $e) {
            $this->server->getLogger()->exception($e, ['clientId' => $clientId, 'context' => 'HttpHandler::handle']);
            
            try {
                $errorResponse = "HTTP/1.1 500 Internal Server Error\r\n";
                $errorResponse .= "Content-Type: text/plain\r\n";
                $errorResponse .= "Connection: close\r\n\r\n";
                $errorResponse .= "An error occurred while processing your request.";
                
                fwrite($client, $errorResponse);
            } catch (Throwable $innerEx) {
                $this->server->getLogger()->error("Failed to send error response: " . $innerEx->getMessage());
            }
        }
    }
}
