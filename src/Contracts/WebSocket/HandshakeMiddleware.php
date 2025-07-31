<?php
/**
 * HandshakeMiddleware interface
 *
 * This interface defines the contract for WebSocket handshake middleware in the Sockeon framework.
 * Handshake middleware allows users to customize and validate WebSocket handshake requests before
 * the connection is established.
 *
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Contracts\WebSocket;

use Sockeon\Sockeon\Connection\Server;
use Sockeon\Sockeon\WebSocket\HandshakeRequest;

interface HandshakeMiddleware
{
    /**
     * Handle the WebSocket handshake request.
     *
     * This method is called during the WebSocket handshake process, before the connection
     * is established. Middleware can perform authentication, validation, or modify the
     * handshake process.
     *
     * @param int $clientId The ID of the client attempting to connect.
     * @param HandshakeRequest $request The handshake request containing headers and request data.
     * @param callable $next The next middleware or handler to call.
     * @param Server $server The server instance handling the WebSocket handshake.
     * @return bool|array True to continue handshake, false to reject, or array with custom response
     */
    public function handle(int $clientId, HandshakeRequest $request, callable $next, Server $server): bool|array;
}
