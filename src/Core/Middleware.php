<?php
/**
 * Middleware class
 * 
 * Manages middleware chains for WebSocket and HTTP pipelines
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core;

use Closure;
use InvalidArgumentException;
use Sockeon\Sockeon\Core\Contracts\HttpMiddleware;
use Sockeon\Sockeon\Core\Contracts\WebsocketMiddleware;
use Sockeon\Sockeon\Http\Request;

class Middleware
{
    /**
     * Stack of WebSocket middleware functions
     * @var array<int, class-string>
     */
    protected array $wsStack = [];
    
    /**
     * Stack of HTTP middleware functions
     * @var array<int, class-string>
     */
    protected array $httpStack = [];
    
    /**
     * Add a WebSocket middleware
     * 
     * @param string $middleware Middleware instance implementing the WebsocketMiddleware interface
     * @return void
     */
    public function addWebSocketMiddleware(string $middleware): void
    {
        if(!is_subclass_of($middleware, WebsocketMiddleware::class)) {
            throw new InvalidArgumentException(sprintf("Middleware '%s' must implement the WebsocketMiddleware interface", $middleware));
        }
        $this->wsStack[] = $middleware;
    }

    /**
     * Add an HTTP middleware
     *
     * @param class-string $middleware Middleware instance implementing the HttpMiddleware interface
     * @return void
     */
    public function addHttpMiddleware(string $middleware): void
    {
        if (!is_subclass_of($middleware, HttpMiddleware::class)) {
            throw new InvalidArgumentException(sprintf("Middleware '%s' must implement the HttpMiddleware interface", $middleware));
        }
        $this->httpStack[] = $middleware;
    }
    
    /**
     * Execute the WebSocket middleware stack
     * 
     * @param int $clientId Client ID
     * @param string $event Event name
     * @param array<string, mixed> $data Event data
     * @param Closure $target Target function to execute at the end of the middleware chain
     * @param Server $server Server instance handling the WebSocket connection
     * @param array<int, class-string> $additionalMiddlewares Optional additional middlewares to include in the stack
     * @return mixed Result of the target function or middleware if chain is interrupted
     */
    public function runWebSocketStack(int $clientId, string $event, array $data, Closure $target, Server $server, array $additionalMiddlewares = []): mixed
    {
        $stack = array_merge($this->wsStack, $additionalMiddlewares);
        
        $run = function (int $index) use (&$run, $stack, $clientId, $event, $data, $target, $server) {
            if ($index >= count($stack)) {
                return $target($clientId, $data);
            }
            
            $middleware = $stack[$index];
            
            $next = function () use ($index, $run) {
                return $run($index + 1);
            };

            /** @var WebsocketMiddleware $object */
            $object = new $middleware();
            
            return $object->handle($clientId, $event, $data, $next, $server);
        };
        
        return $run(0);
    }
    
    /**
     * Execute the HTTP middleware stack
     * 
     * @param Request $request Request object
     * @param Closure $target Target function to execute at the end of the middleware chain
     * @param Server $server Server instance handling the HTTP request
     * @param array<int, class-string> $additionalMiddlewares Optional additional middlewares to include in the stack
     * @return mixed Result of the target function or middleware if chain is interrupted
     */
    public function runHttpStack(Request $request, Closure $target, Server $server, array $additionalMiddlewares = []): mixed
    {
        $stack = array_merge($this->httpStack, $additionalMiddlewares);
        
        $run = function (int $index) use (&$run, $stack, $request, $target, $server) {
            if ($index >= count($stack)) {
                return $target($request);
            }
            
            $middleware = $stack[$index];
            
            $next = function () use ($index, $run) {
                return $run($index + 1);
            };

            /** @var HttpMiddleware $object */
            $object = new $middleware();
            
            return $object->handle($request, $next, $server);
        };
        
        return $run(0);
    }
}
