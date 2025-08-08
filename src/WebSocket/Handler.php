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

            if (empty($data)) {
                $this->server->getLogger()->debug("Empty data received from client: $clientId");
                return true;
            }

            $frames = $this->decodeWebSocketFrame($data);
            if (empty($frames)) {
                $this->server->getLogger()->debug("No valid frames decoded from client: $clientId");
                return true;
            }

            foreach ($frames as $frame) {
                $opcode = $frame['opcode'] ?? 0;
                $payload = $frame['payload'] ?? '';
                
                if (!is_string($payload)) {
                    $payload = is_scalar($payload) ? (string) $payload : '';
                }
                
                switch ($opcode) {
                    case 8:
                        $this->server->getLogger()->debug("Received close frame from client: $clientId");
                        return false;
                        
                    case 9:
                        $this->server->getLogger()->debug("Received ping from client: $clientId");
                        $this->sendPong($client, $payload);
                        break;
                        
                    case 10:
                        $this->server->getLogger()->debug("Received pong from client: $clientId");
                        break;
                        
                    case 1:
                    case 2:
                        if (!empty($payload)) {
                            $this->handleMessage($clientId, $payload);
                        } else {
                            $this->server->getLogger()->debug("Empty payload in text/binary frame from client: $clientId");
                        }
                        break;
                        
                    case 0:
                        $this->server->getLogger()->debug("Received continuation frame from client: $clientId");
                        // TODO: Implement fragmented message handling
                        break;
                        
                    default:
                        $this->server->getLogger()->warning("Unknown opcode received from client: $clientId", [
                            'opcode' => $opcode
                        ]);
                        break;
                }
            }

            return true;
        } catch (Throwable $e) {
            $this->server->getLogger()->exception($e, [
                'clientId' => $clientId, 
                'context' => 'WebSocketHandler::handle',
                'data_length' => strlen($data)
            ]);
            
            try {
                $this->sendErrorMessage($clientId, 'Internal server error processing WebSocket frame');
            } catch (Throwable $sendError) {
                $this->server->getLogger()->warning("Failed to send error message to client: $clientId", [
                    'error' => $sendError->getMessage()
                ]);
            }
            
            return true;
        }
    }

    /**
     * Public method for testing - check if opcode is valid
     * 
     * @param int $opcode The opcode to validate
     * @return bool True if the opcode is valid
     */
    public function isValidOpcode(int $opcode): bool
    {
        return in_array($opcode, [0, 1, 2, 8, 9, 10], true);
    }
}
