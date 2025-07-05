<?php

/**
 * HttpMiddleware interface
 *
 * This interface defines the contract for HTTP middleware in the Sockeon framework.
 * Middleware can be used to modify requests, perform checks, or handle responses.
 *
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Contracts\Http;

use Sockeon\Sockeon\Connection\Server;
use Sockeon\Sockeon\Http\Request;

interface HttpMiddleware
{
    /**
     * Handle the HTTP request
     *
     * This method is called for each HTTP request that passes through this middleware.
     * It can modify the request, perform checks, or pass it to the next middleware.
     *
     * @param Request $request The HTTP request object
     * @param callable $next The next middleware handler to call
     * @param Server $server The server instance handling the HTTP request
     * @return mixed The response from the next middleware or a modified response
     */
    public function handle(Request $request, callable $next, Server $server): mixed;
}