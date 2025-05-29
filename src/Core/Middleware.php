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
use Sockeon\Sockeon\Http\Request;

class Middleware
{
    /**
     * Stack of WebSocket middleware functions
     * @var array<int, Closure>
     */
    protected array $wsStack = [];
    
    /**
     * Stack of HTTP middleware functions
     * @var array<int, Closure>
     */
    protected array $httpStack = [];
    
    /**
     * Add a WebSocket middleware
     * 
     * @param Closure $middleware Middleware function with signature fn($clientId, $event, $data, $next)
     * @return void
     */
    public function addWebSocketMiddleware(Closure $middleware): void
    {
        $this->wsStack[] = $middleware;
    }
    
    /**
     * Add an HTTP middleware
     * 
     * @param Closure $middleware Middleware function with signature fn($request, $next)
     * @return void
     */
    public function addHttpMiddleware(Closure $middleware): void
    {
        $this->httpStack[] = $middleware;
    }
    
    /**
     * Execute the WebSocket middleware stack
     * 
     * @param int $clientId Client ID
     * @param string $event Event name
     * @param array<string, mixed> $data Event data
     * @param Closure $target Target function to execute at the end of the middleware chain
     * @return mixed Result of the target function or middleware if chain is interrupted
     */
    public function runWebSocketStack(int $clientId, string $event, array $data, Closure $target): mixed
    {
        $stack = $this->wsStack;
        
        $run = function (int $index) use (&$run, $stack, $clientId, $event, $data, $target) {
            if ($index >= count($stack)) {
                return $target($clientId, $data);
            }
            
            $middleware = $stack[$index];
            
            $next = function () use ($index, $run) {
                return $run($index + 1);
            };
            
            return $middleware($clientId, $event, $data, $next);
        };
        
        return $run(0);
    }
    
    /**
     * Execute the HTTP middleware stack
     * 
     * @param Request $request Request object
     * @param Closure $target Target function to execute at the end of the middleware chain
     * @return mixed Result of the target function or middleware if chain is interrupted
     */
    public function runHttpStack(Request $request, Closure $target): mixed
    {
        $stack = $this->httpStack;
        
        $run = function (int $index) use (&$run, $stack, $request, $target) {
            if ($index >= count($stack)) {
                return $target($request);
            }
            
            $middleware = $stack[$index];
            
            $next = function () use ($index, $run) {
                return $run($index + 1);
            };
            
            return $middleware($request, $next);
        };
        
        return $run(0);
    }
}
