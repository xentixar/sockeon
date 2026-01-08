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
    use HandlesWebSocketFrames;
    use HandlesWebSocketHandshake;
    use HandlesWebSocketMessages;
    /**
     * Reference to the server instance
     * @var Server
     */
    protected Server $server;

    /**
     * Tracks completed handshakes by client ID
     * @var array<string, bool>
     */
    protected array $handshakes = [];

    /**
     * Buffers incomplete WebSocket frames per client
     * @var array<string, string>
     */
    protected array $frameBuffers = [];

    /**
     * Buffers fragmented messages being reassembled per client
     * @var array<string, array{opcode: int, payload: string, fin: bool}>
     */
    protected array $messageBuffers = [];

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
     * @param string $clientId The client ID
     * @param resource $client The client socket resource
     * @param string $data The received data
     * @return bool Whether to keep the connection alive
     */
    public function handle(string $clientId, $client, string $data): bool
    {
        try {
            if (!isset($this->handshakes[$clientId])) {
                return $this->performHandshake($clientId, $client, $data);
            }

            if (empty($data)) {
                $this->server->getLogger()->debug("Empty data received from client: $clientId");
                return true;
            }

            // Buffer incomplete frames
            if (!isset($this->frameBuffers[$clientId])) {
                $this->frameBuffers[$clientId] = '';
            }

            // Prepend buffered data to new data
            $data = $this->frameBuffers[$clientId] . $data;
            $this->frameBuffers[$clientId] = '';

            // Only try to decode if we have at least 2 bytes (minimum frame header size)
            if (strlen($data) < 2) {
                // Not enough data even for a frame header, buffer it
                $this->frameBuffers[$clientId] = $data;
                $this->server->getLogger()->debug("Buffering data (insufficient for frame header) for client: $clientId", [
                    'buffered_bytes' => strlen($data),
                ]);
                return true;
            }

            [$frames, $remainingData] = $this->decodeWebSocketFrame($data);

            // Buffer remaining data (incomplete frame) for next read
            if (strlen($remainingData) > 0) {
                $this->frameBuffers[$clientId] = $remainingData;
                // Only log if we decoded some frames (partial success) or if buffer is getting large
                if (count($frames) > 0 || strlen($remainingData) > 1024) {
                    $this->server->getLogger()->debug("Buffering incomplete frame for client: $clientId", [
                        'buffered_bytes' => strlen($remainingData),
                        'frames_decoded' => count($frames),
                    ]);
                }
            }

            if (empty($frames)) {
                // Don't log if we're just waiting for more data (normal fragmentation)
                // Only log if we have buffered data that's not being processed
                if (strlen($remainingData) > 0 && strlen($remainingData) > 1024) {
                    $this->server->getLogger()->debug("Waiting for more data to complete frame for client: $clientId", [
                        'buffered_bytes' => strlen($remainingData),
                    ]);
                }
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
                        // Clear buffers on disconnect
                        unset($this->frameBuffers[$clientId]);
                        unset($this->messageBuffers[$clientId]);
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
                        // Check if this is the start of a fragmented message (FIN bit not set)
                        $fin = $frame['fin'] ?? true;
                        if (!$fin) {
                            // Start of fragmented message
                            // If there's already a fragmented message in progress, log warning and clear it
                            if (isset($this->messageBuffers[$clientId])) {
                                $this->server->getLogger()->warning("New fragmented message started while previous one was in progress from client: $clientId", [
                                    'previous_opcode' => $this->messageBuffers[$clientId]['opcode'],
                                    'previous_payload_length' => strlen($this->messageBuffers[$clientId]['payload']),
                                    'new_opcode' => $opcode,
                                ]);
                            }

                            $this->messageBuffers[$clientId] = [ //@phpstan-ignore-line
                                'opcode' => $opcode,
                                'payload' => $payload,
                                'fin' => false,
                            ];
                            $this->server->getLogger()->debug("Started fragmented message from client: $clientId", [
                                'opcode' => $opcode,
                                'initial_payload_length' => strlen($payload),
                            ]);
                        } else {
                            // Complete message (not fragmented)
                            // Clear any existing fragmented message buffer (protocol violation recovery)
                            if (isset($this->messageBuffers[$clientId])) {
                                $this->server->getLogger()->warning("Received complete message while fragmented message was in progress from client: $clientId", [
                                    'previous_opcode' => $this->messageBuffers[$clientId]['opcode'],
                                    'previous_payload_length' => strlen($this->messageBuffers[$clientId]['payload']),
                                ]);
                                unset($this->messageBuffers[$clientId]);
                            }

                            if (!empty($payload)) {
                                $this->handleMessage($clientId, $payload);
                            } else {
                                $this->server->getLogger()->debug("Empty payload in text/binary frame from client: $clientId");
                            }
                        }
                        break;

                    case 0:
                        // Continuation frame - part of a fragmented message
                        $this->server->getLogger()->debug("Received continuation frame from client: $clientId");
                        $this->handleFragmentedMessage($clientId, $frame);
                        break;

                    default:
                        $this->server->getLogger()->warning("Unknown opcode received from client: $clientId", [
                            'opcode' => $opcode,
                        ]);
                        break;
                }
            }

            return true;
        } catch (Throwable $e) {
            $this->server->getLogger()->exception($e, [
                'clientId' => $clientId,
                'context' => 'WebSocketHandler::handle',
                'data_length' => strlen($data),
            ]);

            // Clear buffers on error to prevent corruption
            unset($this->frameBuffers[$clientId]);
            unset($this->messageBuffers[$clientId]);

            try {
                $this->sendErrorMessage($clientId, 'Internal server error processing WebSocket frame');
            } catch (Throwable $sendError) {
                $this->server->getLogger()->warning("Failed to send error message to client: $clientId", [
                    'error' => $sendError->getMessage(),
                ]);
            }

            return true;
        }
    }

    /**
     * Handle a fragmented message continuation frame
     *
     * @param string $clientId The client ID
     * @param array<string, mixed> $frame The continuation frame
     * @return void
     */
    protected function handleFragmentedMessage(string $clientId, array $frame): void
    {
        if (!isset($this->messageBuffers[$clientId])) {
            $this->server->getLogger()->warning("Received continuation frame without initial frame from client: $clientId");
            return;
        }

        /** @var bool $fin */
        $fin = $frame['fin'] ?? true;
        $payload = $frame['payload'] ?? '';

        if (!is_string($payload)) {
            $payload = is_scalar($payload) ? (string) $payload : '';
        }

        // Check total message size limit
        $currentSize = strlen($this->messageBuffers[$clientId]['payload']);
        $newSize = $currentSize + strlen($payload);
        $maxMessageSize = $this->server->getMaxMessageSize();

        if ($newSize > $maxMessageSize) {
            $this->server->getLogger()->warning("Fragmented message exceeds size limit from client: $clientId", [
                'current_size' => $currentSize,
                'additional_size' => strlen($payload),
                'total_size' => $newSize,
                'limit' => $maxMessageSize,
            ]);

            // Clear the buffer and send error
            unset($this->messageBuffers[$clientId]);
            $this->sendErrorMessage($clientId, 'Fragmented message exceeds maximum size limit');
            return;
        }

        // Append continuation frame payload
        $this->messageBuffers[$clientId]['payload'] .= $payload;
        $this->messageBuffers[$clientId]['fin'] = $fin;

        if ($fin) {
            // Last frame - reassemble and process complete message
            $completePayload = $this->messageBuffers[$clientId]['payload'];
            $this->server->getLogger()->debug("Completed fragmented message from client: $clientId", [
                'total_payload_length' => strlen($completePayload),
                'opcode' => $this->messageBuffers[$clientId]['opcode'],
            ]);

            if (!empty($completePayload)) {
                $this->handleMessage($clientId, $completePayload);
            }

            // Clear the buffer
            unset($this->messageBuffers[$clientId]);
        } else {
            $this->server->getLogger()->debug("Received continuation frame (more to come) from client: $clientId", [
                'current_payload_length' => strlen($this->messageBuffers[$clientId]['payload']),
            ]);
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
