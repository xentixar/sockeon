<?php
/**
 * Http1Handler class
 * 
 * Handles HTTP/1.1 protocol implementation, request parsing and responses
 * 
 * Features:
 * - HTTP/1.1 request parsing
 * - Query parameter extraction
 * - Path normalization
 * - JSON body parsing
 * - Response generation
 * - CORS support
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http;

use Sockeon\Sockeon\Connection\Server;
use Sockeon\Sockeon\Http\Request;
use Sockeon\Sockeon\Http\Response;
use Sockeon\Sockeon\Http\CorsConfig;
use Sockeon\Sockeon\Traits\Http\HandlesCors;
use Sockeon\Sockeon\Traits\Http\HandlesHttpRequests;
use Sockeon\Sockeon\Traits\Http\HandlesHttpLogging;
use Throwable;

class Http1Handler
{
    use HandlesCors, HandlesHttpRequests, HandlesHttpLogging;
    /**
     * Reference to the server instance
     * @var Server
     */
    protected Server $server;
    
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
     * Handle an incoming HTTP/1.1 request
     * 
     * @param int $clientId The client identifier
     * @param resource $client The client socket resource
     * @param string $data The raw HTTP request data
     * @return void
     */
    public function handle(int $clientId, $client, string $data): void
    {
        try {
            $this->debug("Received HTTP/1.1 request from client #{$clientId}");
            
            $requestData = $this->parseHttpRequest($data);
            $request = new Request($requestData);
            
            if ($request->getMethod() === 'OPTIONS') {
                $this->debug("Handling preflight OPTIONS request");
                $response = $this->handleCorsPreflightRequest($request);
            } else {
                $this->debug("Processing standard HTTP/1.1 request");
                $response = $this->processRequest($request);
                
                $this->debug("Applying CORS headers");
                $response = $this->applyCorsHeaders($request, $response);
            }
            
            fwrite($client, $response);
        } catch (Throwable $e) {
            $this->server->getLogger()->exception($e, ['clientId' => $clientId, 'context' => 'Http1Handler::handle']);
            
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

    /**
     * Get CORS configuration
     * 
     * @return CorsConfig
     */
    public function getCorsConfig(): CorsConfig
    {
        return $this->corsConfig;
    }
}
