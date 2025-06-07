<?php
/**
 * HttpRoute attribute class
 * 
 * Attribute for marking methods as HTTP route handlers
 * Used to associate controller methods with specific HTTP routes
 * 
 * Support for path parameters using {parameter} syntax:
 * - Example: "/users/{id}" will match "/users/123" and pass "id" => "123" as parameter
 * - Query parameters are automatically extracted from the URL
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class HttpRoute
{
    /**
     * Constructor
     * 
     * @param string $method The HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $path The URL path to handle, can include path parameters like {id}
     * @param array<int, class-string> $middlewares Optional array of middleware class names to apply to this route
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $middlewares = [],
    ) {}
}
