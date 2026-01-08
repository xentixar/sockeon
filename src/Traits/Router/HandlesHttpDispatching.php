<?php

/**
 * HandlesHttpDispatching trait
 *
 * Manages HTTP request dispatching and route matching
 *
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Traits\Router;

use Sockeon\Sockeon\Http\Request;

trait HandlesHttpDispatching
{
    /**
     * Dispatch an HTTP request to the appropriate handler
     *
     * @param Request $request The Request object
     * @return mixed The response from the handler
     */
    public function dispatchHttp(Request $request): mixed
    {
        $method = $request->getMethod();
        $path = $request->getPath();
        $key = $method . ' ' . $path;

        if (isset($this->httpRoutes[$key])) {
            return $this->executeHttpRoute($this->httpRoutes[$key], $request);
        }

        foreach ($this->httpRoutes as $routeKey => $handler) {
            [$routeMethod, $routePath] = explode(' ', $routeKey, 2);

            if ($routeMethod !== $method) {
                continue;
            }

            $pathParams = [];
            if ($this->matchRoute($routePath, $path, $pathParams)) {
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
     * @param array{0: \Sockeon\Sockeon\Controllers\SocketController, 1: string, 2: array<int, class-string>, 3: array<int, class-string>} $handler The controller and method to call
     * @param Request $request The Request object
     * @return mixed The response from the handler
     */
    private function executeHttpRoute(array $handler, Request $request): mixed
    {
        [$controller, $method, $middlewares, $excludeGlobalMiddlewares] = $handler;

        $this->validateHttpMiddlewares($middlewares);

        if ($this->server) {
            return $this->server->getMiddleware()->runHttpStack(
                $request,
                function ($request) use ($controller, $method) {
                    return $controller->$method($request);
                },
                $this->server,
                $middlewares,
                $excludeGlobalMiddlewares
            );
        } else {
            return $controller->$method($request);
        }
    }

    /**
     * Match a route pattern against a path
     *
     * Supports path parameters in the format {paramName}
     *
     * @param string $pattern The route pattern with parameters
     * @param string $path The actual request path
     * @param array<string, string> &$params Reference to array for extracted parameters
     * @return bool True if the route matches, false otherwise
     */
    private function matchRoute(string $pattern, string $path, array &$params): bool
    {
        $regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
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
     * @return array<string, array{0: \Sockeon\Sockeon\Controllers\SocketController, 1: string, 2: array<int, class-string>, 3: array<int, class-string>}> The registered HTTP routes
     */
    public function getHttpRoutes(): array
    {
        return $this->httpRoutes;
    }
}
