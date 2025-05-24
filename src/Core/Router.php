<?php
/**
 * Router class
 * 
 * Manages routing of WebSocket events and HTTP requests to controller methods
 * 
 * Features:
 * - WebSocket event routing
 * - HTTP request routing with method and path matching
 * - Support for path parameters using {parameter} syntax
 * - Automatic query parameter parsing
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core;

use ReflectionClass;
use Sockeon\Sockeon\WebSocket\Attributes\SocketOn;
use Sockeon\Sockeon\Http\Attributes\HttpRoute;
use Sockeon\Sockeon\Http\Request;
use Sockeon\Sockeon\Core\Contracts\SocketController;

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
     * @param Request $request  The Request object
     * @return mixed            The response from the handler
     */
    public function dispatchHttp(Request $request): mixed
    {
        $method = $request->getMethod();
        $path = $request->getPath();
        $key = $method . ' ' . $path;
        
        // Check for direct match first
        if (isset($this->httpRoutes[$key])) {
            return $this->executeHttpRoute($this->httpRoutes[$key], $request);
        }
        
        // Check for routes with path parameters
        foreach ($this->httpRoutes as $routeKey => $handler) {
            [$routeMethod, $routePath] = explode(' ', $routeKey, 2);
            
            // Skip routes with different HTTP methods
            if ($routeMethod !== $method) {
                continue;
            }
            
            $pathParams = [];
            if ($this->matchRoute($routePath, $path, $pathParams)) {
                // Add path parameters to the request by updating the Request object
                $requestData = $request->toArray();
                $requestData['params'] = $pathParams;
                $request = Request::fromArray($requestData);
                
                return $this->executeHttpRoute($handler, $request);
            }
        }
        
        return null;
    }
    
    /**
     * Execute an HTTP route handler with middleware
     * 
     * @param array $handler    The controller and method to call
     * @param Request $request  The Request object
     * @return mixed            The response from the handler
     */
    private function executeHttpRoute(array $handler, Request $request): mixed
    {
        [$controller, $method] = $handler;
        
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
    
    /**
     * Match a route pattern against a path
     * 
     * Supports path parameters in the format {paramName}
     * 
     * @param string $pattern     The route pattern with parameters
     * @param string $path        The actual request path
     * @param array  &$params     Reference to array for extracted parameters
     * @return bool               True if the route matches, false otherwise
     */
    private function matchRoute(string $pattern, string $path, array &$params): bool
    {
        // Convert route pattern to regex
        $regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        
        if (preg_match($regex, $path, $matches)) {
            // Extract named parameters
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            return true;
        }
        
        return false;
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
