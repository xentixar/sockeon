<?php
/**
 * HandlesMiddlewareValidation trait
 * 
 * Manages middleware validation for HTTP and WebSocket routes
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Traits\Router;

use InvalidArgumentException;
use Sockeon\Sockeon\Contracts\Http\HttpMiddleware;
use Sockeon\Sockeon\Contracts\WebSocket\WebsocketMiddleware;

trait HandlesMiddlewareValidation
{
    /**
     * Validate HTTP middlewares
     *
     * @param array<int, class-string> $middlewares
     * @return void
     */
    private function validateHttpMiddlewares(array $middlewares): void
    {
        foreach ($middlewares as $middleware) {
            if (!is_subclass_of($middleware, HttpMiddleware::class)) {
                throw new InvalidArgumentException(
                    sprintf('Middleware %s must implement HttpMiddleware interface', $middleware)
                );
            }
        }
    }

    /**
     * Validate WebSocket middlewares
     *
     * @param array<int, class-string> $middlewares
     * @return void
     */
    public function validateWebsocketMiddlewares(array $middlewares): void
    {
        foreach ($middlewares as $middleware) {
            if (!is_subclass_of($middleware, WebsocketMiddleware::class)) {
                throw new InvalidArgumentException(
                    sprintf('Middleware %s must implement WebsocketMiddleware interface', $middleware)
                );
            }
        }
    }
}
