<?php
/**
 * HttpRoute attribute class
 * 
 * Attribute for marking methods as HTTP route handlers
 * Used to associate controller methods with specific HTTP routes
 * 
 * @package     Xentixar\Socklet
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Xentixar\Socklet\Http\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class HttpRoute
{
    /**
     * Constructor
     * 
     * @param string $method  The HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $path    The URL path to handle
     */
    public function __construct(
        public string $method,
        public string $path
    ) {}
}
