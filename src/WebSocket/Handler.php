<?php
/**
 * WebSocketHandler class
 * 
 * Handles WebSocket protocol implementation, connections and message framing
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\WebSocket;

use Sockeon\Sockeon\Connection\Server;
use Sockeon\Sockeon\Core\Config;
use Sockeon\Sockeon\Traits\WebSocket\HandlesWebSocketFrames;
use Sockeon\Sockeon\Traits\WebSocket\HandlesWebSocketHandshake;
use Sockeon\Sockeon\Traits\WebSocket\HandlesWebSocketMessages;
use Throwable;

class Handler
{
    use HandlesWebSocketFrames, HandlesWebSocketHandshake, HandlesWebSocketMessages;
    /**
     * Reference to the server instance
     * @var Server
     */
    protected Server $server;

    /**
     * Tracks completed handshakes by client ID
     * @var array<int, bool>
     */
    protected array $handshakes = [];

    /**
     * Allowed origins for WebSocket connections
     * @var array<int, string>
     */
    protected array $allowedOrigins = ['*'];

    /**
     * Constructor
     * 
     * @param Server $server  The server instance
     * @param array<int, string> $allowedOrigins Allowed origins for WebSocket connections
     */
    public function __construct(Server $server, array $allowedOrigins = ['*'])
    {
        $this->server = $server;
        $this->allowedOrigins = $allowedOrigins;
    }

    /**
     * Check if the origin is allowed
     *
     * @param string $origin
     * @return bool
     */
    protected function isOriginAllowed(string $origin): bool
    {
        if (in_array('*', $this->allowedOrigins)) {
            return true;
        }

        return in_array($origin, $this->allowedOrigins);
    }

    /**
     * Handle data from a WebSocket client
     * 
     * @param int $clientId The client ID
     * @param resource $client The client socket resource
     * @param string $data The received data
     * @return bool Whether to keep the connection alive
     */
    public function handle(int $clientId, $client, string $data): bool
    {
        try {
            if (!isset($this->handshakes[$clientId])) {
                return $this->performHandshake($clientId, $client, $data);
            }

            $frames = $this->decodeWebSocketFrame($data);
            if (empty($frames)) {
                return true;
            }

            foreach ($frames as $frame) {
                if ($frame['opcode'] === 8) {
                    $this->server->getLogger()->debug("Received close frame from client: $clientId");
                    return false;
                } elseif ($frame['opcode'] === 9) {
                    $this->server->getLogger()->debug("Received ping from client: $clientId");
                    $payload = isset($frame['payload']) ? (is_string($frame['payload']) ? $frame['payload'] : '') : '';
                    $pongFrame = $this->encodeWebSocketFrame($payload, 10);
                    fwrite($client, $pongFrame);
                } elseif ($frame['opcode'] === 10) {
                    $this->server->getLogger()->debug("Received pong from client: $clientId");
                } elseif ($frame['opcode'] === 1 || $frame['opcode'] === 2) {
                    if ($frame['payload'] ?? false) {
                        $payload = is_string($frame['payload']) ? $frame['payload'] : '';
                        if (!empty($payload)) {
                            $this->handleMessage($clientId, $payload);
                        }
                    }
                }
            }

            return true;
        } catch (Throwable $e) {
            $this->server->getLogger()->exception($e, ['clientId' => $clientId, 'context' => 'WebSocketHandler::handle']);
            
            return true;
        }
    }
}
