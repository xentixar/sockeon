<?php
/**
 * Middleware class
 * 
 * Manages middleware chains for WebSocket and HTTP pipelines
 * 
 * @package     Xentixar\Socklet
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Xentixar\Socklet\Core;

use Closure;
use Xentixar\Socklet\Http\Request;

class Middleware
{
    /**
     * Stack of WebSocket middleware functions
     * @var array
     */
    protected array $wsStack = [];
    
    /**
     * Stack of HTTP middleware functions
     * @var array
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
     * @param array $data Event data
     * @param Closure $target Target function to execute at the end of the middleware chain
     * @return mixed Result of the target function or middleware if chain is interrupted
     */
    public function runWebSocketStack(int $clientId, string $event, array $data, Closure $target): mixed
    {
        $stack = $this->wsStack;
        
        // Define the executor function that will iterate through middleware
        $run = function (int $index) use (&$run, $stack, $clientId, $event, $data, $target) {
            if ($index >= count($stack)) {
                // End of middleware stack, execute the target
                return $target($clientId, $data);
            }
            
            // Execute the current middleware
            $middleware = $stack[$index];
            
            // The next function for this middleware
            $next = function () use ($index, $run, $clientId, $event, $data) {
                return $run($index + 1);
            };
            
            return $middleware($clientId, $event, $data, $next);
        };
        
        // Start execution at the beginning of the stack
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
        
        // Define the executor function that will iterate through middleware
        $run = function (int $index) use (&$run, $stack, $request, $target) {
            if ($index >= count($stack)) {
                // End of middleware stack, execute the target
                return $target($request);
            }
            
            // Execute the current middleware
            $middleware = $stack[$index];
            
            // The next function for this middleware
            $next = function () use ($index, $run, $request) {
                return $run($index + 1);
            };
            
            return $middleware($request, $next);
        };
        
        // Start execution at the beginning of the stack
        return $run(0);
    }
}
