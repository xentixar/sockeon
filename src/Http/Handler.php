<?php
/**
 * HTTP Protocol Handler for Sockeon Framework
 * 
 * Routes HTTP requests to appropriate protocol handlers (HTTP/1.1 or HTTP/2)
 * based on protocol detection and negotiation
 * 
 * Features:
 * - Protocol detection and routing
 * - HTTP/1.1 and HTTP/2 support
 * - Connection preface parsing
 * - Protocol negotiation
 * - Handler delegation
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http;

use Sockeon\Sockeon\Connection\Server;
use Sockeon\Sockeon\Http\Http1Handler;
use Sockeon\Sockeon\Http\Http2Handler;
use Throwable;

class Handler
{
    /**
     * Reference to the server instance
     * @var Server
     */
    protected Server $server;
    
    /**
     * HTTP/1.1 handler instance
     * @var Http1Handler
     */
    protected Http1Handler $http1Handler;
    
    /**
     * HTTP/2 handler instance
     * @var Http2Handler
     */
    protected Http2Handler $http2Handler;

    /**
     * Constructor
     * 
     * @param Server $server The server instance
     * @param array<string, mixed> $corsConfig Optional CORS configuration
     */
    public function __construct(Server $server, array $corsConfig = [])
    {
        $this->server = $server;
        $this->http1Handler = new Http1Handler($server, $corsConfig);
        $this->http2Handler = new Http2Handler($server);
    }

    /**
     * Handle an incoming HTTP request by routing to appropriate protocol handler
     * 
     * @param int $clientId The client identifier
     * @param resource $client The client socket resource
     * @param string $data The raw HTTP request data
     * @return void
     */
    public function handle(int $clientId, $client, string $data): void
    {
        try {
            if ($this->isHttp2Connection($data)) {
                $this->debug("Detected HTTP/2 connection from client #{$clientId}");
                $this->http2Handler->handle($clientId, $client, $data);
                return;
            }

            $this->debug("Routing to HTTP/1.1 handler for client #{$clientId}");
            $this->http1Handler->handle($clientId, $client, $data);
            
        } catch (Throwable $e) {
            $this->server->getLogger()->exception($e, ['clientId' => $clientId, 'context' => 'Handler::handle']);
            
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
     * Check if the incoming connection is HTTP/2
     * 
     * @param string $data The raw connection data
     * @return bool True if HTTP/2 connection, false otherwise
     */
    protected function isHttp2Connection(string $data): bool
    {
        return str_starts_with($data, 'PRI * HTTP/2.0');
    }

    /**
     * Get the HTTP/1.1 handler instance
     * 
     * @return Http1Handler
     */
    public function getHttp1Handler(): Http1Handler
    {
        return $this->http1Handler;
    }

    /**
     * Get the HTTP/2 handler instance
     * 
     * @return Http2Handler
     */
    public function getHttp2Handler(): Http2Handler
    {
        return $this->http2Handler;
    }

    /**
     * Log debug information if debug mode is enabled
     * 
     * @param string $message The debug message
     * @param mixed|null $data Additional data to log
     * @return void
     */
    protected function debug(string $message, mixed $data = null): void
    {
        try {
            $dataString = $data !== null ? ' ' . json_encode($data) : '';
            $this->server->getLogger()->debug("[Sockeon HTTP Handler] {$message}{$dataString}");
        } catch (Throwable $e) {
            $this->server->getLogger()->error("Failed to log message: " . $e->getMessage());
        }
    }
}
