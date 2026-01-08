<?php

/**
 * WebSocketMiddleware interface
 *
 * This interface defines the contract for Websocket middleware in the Sockeon framework.
 * Middleware can be used to modify requests, perform checks, or handle responses.
 *
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Contracts\WebSocket;

use Sockeon\Sockeon\Connection\Server;

interface WebsocketMiddleware
{
    /**
     * Handle the incoming WebSocket event.
     *
     * @param string $clientId The ID of the client sending the event.
     * @param string $event The name of the event being sent.
     * @param array<string, mixed> $data The data associated with the event.
     * @param callable $next The next middleware or handler to call.
     * @param Server $server The server instance handling the WebSocket connection.
     * @return mixed
     */
    public function handle(string $clientId, string $event, array $data, callable $next, Server $server): mixed;
}
