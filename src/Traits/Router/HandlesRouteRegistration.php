<?php

/**
 * HandlesRouteRegistration trait
 *
 * Manages registration of controllers and their routes using reflection
 *
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Traits\Router;

use ReflectionClass;
use Sockeon\Sockeon\Controllers\SocketController;
use Sockeon\Sockeon\Http\Attributes\HttpRoute;
use Sockeon\Sockeon\WebSocket\Attributes\OnConnect;
use Sockeon\Sockeon\WebSocket\Attributes\OnDisconnect;
use Sockeon\Sockeon\WebSocket\Attributes\SocketOn;

trait HandlesRouteRegistration
{
    /**
     * Register a controller and its routes
     *
     * Uses reflection to find attributes and register handlers
     *
     * @param SocketController $controller The controller instance to register
     * @return void
     */
    public function register(SocketController $controller): void
    {
        $ref = new ReflectionClass($controller);

        foreach ($ref->getMethods() as $method) {
            foreach ($method->getAttributes(SocketOn::class) as $attr) {
                $attrInstance = $attr->newInstance();
                $event = $attrInstance->event;
                $this->wsRoutes[$event] = [$controller, $method->getName(), $attrInstance->middlewares, $attrInstance->excludeGlobalMiddlewares];
            }

            foreach ($method->getAttributes(OnConnect::class) as $attr) {
                $this->specialEventHandlers['connect'][] = [$controller, $method->getName()];
            }

            foreach ($method->getAttributes(OnDisconnect::class) as $attr) {
                $this->specialEventHandlers['disconnect'][] = [$controller, $method->getName()];
            }

            foreach ($method->getAttributes(HttpRoute::class) as $attr) {
                $httpAttr = $attr->newInstance();
                $key = $httpAttr->method . ' ' . $httpAttr->path;
                $this->httpRoutes[$key] = [$controller, $method->getName(), $httpAttr->middlewares, $httpAttr->excludeGlobalMiddlewares];
            }
        }
    }
}
