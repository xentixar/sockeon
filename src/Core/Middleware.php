<?php

/**
 * Middleware class
 *
 * Manages middleware chains for WebSocket and HTTP pipelines
 *
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core;

use Closure;
use InvalidArgumentException;
use Sockeon\Sockeon\Connection\Server;
use Sockeon\Sockeon\Contracts\Http\HttpMiddleware;
use Sockeon\Sockeon\Contracts\WebSocket\HandshakeMiddleware;
use Sockeon\Sockeon\Contracts\WebSocket\WebsocketMiddleware;
use Sockeon\Sockeon\Http\Request;
use Sockeon\Sockeon\WebSocket\HandshakeRequest;

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
     * Stack of WebSocket handshake middleware functions
     * @var array<int, class-string>
     */
    protected array $handshakeStack = [];

    /**
     * Add a WebSocket middleware
     *
     * @param string $middleware Middleware instance implementing the WebsocketMiddleware interface
     * @return void
     */
    public function addWebSocketMiddleware(string $middleware): void
    {
        if (!is_subclass_of($middleware, WebsocketMiddleware::class)) {
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
     * Add a WebSocket handshake middleware
     *
     * @param class-string $middleware Middleware instance implementing the HandshakeMiddleware interface
     * @return void
     */
    public function addHandshakeMiddleware(string $middleware): void
    {
        if (!is_subclass_of($middleware, HandshakeMiddleware::class)) {
            throw new InvalidArgumentException(sprintf("Middleware '%s' must implement the HandshakeMiddleware interface", $middleware));
        }
        $this->handshakeStack[] = $middleware;
    }

    /**
     * Execute the WebSocket middleware stack
     *
     * @param string $clientId Client ID
     * @param string $event Event name
     * @param array<string, mixed> $data Event data
     * @param Closure $target Target function to execute at the end of the middleware chain
     * @param Server $server Server instance handling the WebSocket connection
     * @param array<int, class-string> $additionalMiddlewares Optional additional middlewares to include in the stack
     * @param array<int, class-string> $excludeGlobalMiddlewares Optional array of global middlewares to exclude
     * @return mixed Result of the target function or middleware if chain is interrupted
     */
    public function runWebSocketStack(string $clientId, string $event, array $data, Closure $target, Server $server, array $additionalMiddlewares = [], array $excludeGlobalMiddlewares = []): mixed
    {
        $globalStack = $this->filterExcludedMiddlewares($this->wsStack, $excludeGlobalMiddlewares);
        $stack = array_merge($globalStack, $additionalMiddlewares);

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
     * @param array<int, class-string> $excludeGlobalMiddlewares Optional array of global middlewares to exclude
     * @return mixed Result of the target function or middleware if chain is interrupted
     */
    public function runHttpStack(Request $request, Closure $target, Server $server, array $additionalMiddlewares = [], array $excludeGlobalMiddlewares = []): mixed
    {
        $globalStack = $this->filterExcludedMiddlewares($this->httpStack, $excludeGlobalMiddlewares);
        $stack = array_merge($globalStack, $additionalMiddlewares);

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

    /**
     * Execute the WebSocket handshake middleware stack
     *
     * @param string $clientId Client ID
     * @param HandshakeRequest $request Handshake request object
     * @param Closure $target Target function to execute at the end of the middleware chain
     * @param Server $server Server instance handling the WebSocket handshake
     * @param array<int, class-string> $additionalMiddlewares Optional additional middlewares to include in the stack
     * @return mixed Result of the target function or middleware if chain is interrupted
     */
    public function runHandshakeStack(string $clientId, HandshakeRequest $request, Closure $target, Server $server, array $additionalMiddlewares = []): mixed
    {
        $stack = array_merge($this->handshakeStack, $additionalMiddlewares);

        $run = function (int $index) use (&$run, $stack, $clientId, $request, $target, $server): mixed {
            if ($index >= count($stack)) {
                /** @var mixed */
                return $target($clientId, $request);
            }

            $middleware = $stack[$index];

            $next = function () use ($index, $run): mixed {
                return $run($index + 1);
            };

            /** @var HandshakeMiddleware $object */
            $object = new $middleware();

            return $object->handle($clientId, $request, $next, $server);
        };

        return $run(0);
    }

    /**
     * Filter out excluded middlewares from the global stack
     *
     * @param array<int, class-string> $globalStack The global middleware stack
     * @param array<int, class-string> $excludeMiddlewares Array of middleware class names to exclude
     * @return array<int, class-string> Filtered middleware stack
     */
    private function filterExcludedMiddlewares(array $globalStack, array $excludeMiddlewares): array
    {
        if (empty($excludeMiddlewares)) {
            return $globalStack;
        }

        return array_filter($globalStack, function ($middleware) use ($excludeMiddlewares) {
            return !in_array($middleware, $excludeMiddlewares, true);
        });
    }
}
