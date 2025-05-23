<?php
/**
 * Router class
 * 
 * Manages routing of WebSocket events and HTTP requests to controller methods
 * 
 * @package     Xentixar\Socklet
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Xentixar\Socklet\Core;

use ReflectionClass;
use Xentixar\Socklet\WebSocket\Attributes\SocketOn;
use Xentixar\Socklet\Http\Attributes\HttpRoute;
use Xentixar\Socklet\Core\Contracts\SocketController;

class Router
{
    /**
     * WebSocket routes
     * @var array
     */
    protected array $wsRoutes = [];
    
    /**
     * HTTP routes
     * @var array
     */
    protected array $httpRoutes = [];
    
    /**
     * Server instance
     * @var Server|null
     */
    protected ?Server $server = null;

    /**
     * Set the server instance
     * 
     * @param Server $server  The server instance
     * @return void
     */
    public function setServer(Server $server): void
    {
        $this->server = $server;
    }

    /**
     * Register a controller and its routes
     * 
     * Uses reflection to find attributes and register handlers
     * 
     * @param SocketController $controller  The controller instance to register
     * @return void
     */
    public function register(SocketController $controller): void
    {
        $ref = new ReflectionClass($controller);
        
        // Register WebSocket routes
        foreach ($ref->getMethods() as $method) {
            // Socket routes
            foreach ($method->getAttributes(SocketOn::class) as $attr) {
                $event = $attr->newInstance()->event;
                $this->wsRoutes[$event] = [$controller, $method->getName()];
            }
            
            // HTTP routes
            foreach ($method->getAttributes(HttpRoute::class) as $attr) {
                $httpAttr = $attr->newInstance();
                $key = $httpAttr->method . ' ' . $httpAttr->path;
                $this->httpRoutes[$key] = [$controller, $method->getName()];
            }
        }
    }

    /**
     * Dispatch a WebSocket event to the appropriate handler
     * 
     * @param int    $clientId  The client identifier
     * @param string $event     The event name
     * @param array  $data      The event data
     * @return void
     */
    public function dispatch(int $clientId, string $event, array $data): void
    {
        if (isset($this->wsRoutes[$event])) {
            [$controller, $method] = $this->wsRoutes[$event];
            
            if ($this->server) {
                // Use middleware if server is set
                $this->server->getMiddleware()->runWebSocketStack(
                    $clientId, 
                    $event, 
                    $data, 
                    function ($clientId, $data) use ($controller, $method) {
                        return $controller->$method($clientId, $data);
                    }
                );
            } else {
                // Direct call if no server is set
                $controller->$method($clientId, $data);
            }
        }
    }
    
    /**
     * Dispatch an HTTP request to the appropriate handler
     * 
     * @param array $request  The parsed HTTP request
     * @return mixed          The response from the handler
     */
    public function dispatchHttp(array $request): mixed
    {
        $key = $request['method'] . ' ' . $request['path'];
        
        if (isset($this->httpRoutes[$key])) {
            [$controller, $method] = $this->httpRoutes[$key];
            
            if ($this->server) {
                // Use middleware if server is set
                return $this->server->getMiddleware()->runHttpStack(
                    $request,
                    function ($request) use ($controller, $method) {
                        return $controller->$method($request);
                    }
                );
            } else {
                // Direct call if no server is set
                return $controller->$method($request);
            }
        }
        
        return null;
    }
    
    /**
     * Get all registered HTTP routes
     * 
     * @return array  The registered HTTP routes
     */
    public function getHttpRoutes(): array
    {
        return $this->httpRoutes;
    }
}
