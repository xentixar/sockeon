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
use Throwable;

class Handler
{
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

    /**
     * Perform WebSocket handshake with client
     * 
     * @param int $clientId The client identifier
     * @param resource $client The client socket resource
     * @param string $data The HTTP handshake request
     * @return bool Whether the handshake was successful
     */
    protected function performHandshake(int $clientId, $client, string $data): bool
    {
        $origin = null;
        if (preg_match('/Origin:\s(.+)\r\n/i', $data, $originMatches)) {
            $origin = trim($originMatches[1]);
        }

        if ($origin !== null && !$this->isOriginAllowed($origin)) {
            $response = "HTTP/1.1 403 Forbidden\r\nContent-Type: text/plain\r\n\r\nOrigin not allowed";
            fwrite($client, $response);
            return false;
        }

        $requestUri = null;
        if (preg_match('/GET\s+(.*?)\s+HTTP/i', $data, $uriMatches)) {
            $requestUri = trim($uriMatches[1]);
        }

        $authKey = Config::getAuthKey();
        if ($authKey !== null) {
            $queryString = '';
            if ($requestUri !== null && strpos($requestUri, '?') !== false) {
                $queryString = substr($requestUri, strpos($requestUri, '?') + 1);
            }
            
            parse_str($queryString, $queryParams);
            
            if (!isset($queryParams['key']) || $queryParams['key'] !== $authKey) {
                $response = "HTTP/1.1 401 Unauthorized\r\nContent-Type: text/plain\r\n\r\nInvalid authentication key";
                fwrite($client, $response);
                $this->server->getLogger()->debug("[WebSocket Authentication] Authentication failed for client: $clientId");
                return false;
            }
            
            $this->server->getLogger()->debug("[WebSocket Authentication] Authentication successful for client: $clientId");
        }

        if (preg_match('/Sec-WebSocket-Key:\s(.+)\r\n/i', $data, $matches)) {
            $secKey = trim($matches[1]);
            $acceptKey = base64_encode(sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

            $headers = [
                "HTTP/1.1 101 Switching Protocols",
                "Upgrade: websocket",
                "Connection: Upgrade",
                "Sec-WebSocket-Accept: $acceptKey",
                "Sec-WebSocket-Version: 13",
            ];

            if ($origin !== null && $this->isOriginAllowed($origin)) {
                $headers[] = "Access-Control-Allow-Origin: $origin";
            }

            $response = implode("\r\n", $headers) . "\r\n\r\n";
            fwrite($client, $response);
            $this->handshakes[$clientId] = true;
            
            return true;
        }

        return false;
    }

    /**
     * Decode WebSocket frames from raw binary data
     * 
     * @param string $data The raw WebSocket frame data
     * @return array<int, array<string, mixed>> An array of parsed WebSocket frames
     */
    protected function decodeWebSocketFrame(string $data): array
    {
        $frames = [];
        
        while (strlen($data) > 0) {
            // Make sure we have at least 2 bytes
            if (strlen($data) < 2) {
                break;
            }

            $firstByte = ord($data[0]);
            $secondByte = ord($data[1]);
            
            $fin = ($firstByte & 0x80) == 0x80;
            $opcode = $firstByte & 0x0F;
            $masked = ($secondByte & 0x80) == 0x80;
            $payloadLength = $secondByte & 0x7F;
            
            $offset = 2;
            $extendedPayloadLength = 0;

            // Extended payload length - 16 bits
            if ($payloadLength == 126) {
                // Make sure we have enough bytes for extended 16-bit length
                if (strlen($data) < 4) {
                    break;
                }
                $unpacked = unpack('n', substr($data, 2, 2));
                if (!$unpacked || !isset($unpacked[1])) {
                    break;
                }
                $extendedPayloadLength = $unpacked[1];
                $payloadLength = $extendedPayloadLength;
                $offset += 2;
            }
            // Extended payload length - 64 bits
            elseif ($payloadLength == 127) {
                // Make sure we have enough bytes for extended 64-bit length
                if (strlen($data) < 10) {
                    break;
                }
                $unpacked = unpack('J', substr($data, 2, 8));
                if (!$unpacked || !isset($unpacked[1])) {
                    break;
                }
                $extendedPayloadLength = $unpacked[1];
                $payloadLength = $extendedPayloadLength;
                $offset += 8;
            }
            
            // Check if we have enough data for the entire frame
            $frameLength = $offset;
            if ($masked) {
                $frameLength += 4;  // Add mask key length
            }
            $frameLength += $payloadLength; // @phpstan-ignore-line

            if (strlen($data) < $frameLength) {
                break;
            }

            // Process the frame
            if ($masked) {
                $maskKey = substr($data, $offset, 4);
                $offset += 4;

                $payload = substr($data, $offset, $payloadLength); // @phpstan-ignore-line
                $unmaskedPayload = '';
                
                // Apply mask
                $length = strlen($payload);
                for ($i = 0; $i < $length; $i++) {
                    $unmaskedPayload .= $payload[$i] ^ $maskKey[$i % 4];
                }
                
                $frames[] = [
                    'fin' => $fin,
                    'opcode' => $opcode,
                    'masked' => $masked,
                    'payload' => $unmaskedPayload
                ];
            } else {
                $payload = substr($data, $offset, $payloadLength);  // @phpstan-ignore-line
                $frames[] = [
                    'fin' => $fin,
                    'opcode' => $opcode,
                    'masked' => $masked,
                    'payload' => $payload
                ];
            }
            
            // Move to the next frame
            $data = substr($data, $frameLength);
        }
        
        return $frames;
    }

    /**
     * Encode a message into a WebSocket frame
     * 
     * @param string $payload The payload to encode
     * @param int $opcode The WebSocket opcode (1=text, 8=close, 9=ping, 10=pong)
     * @return string The encoded WebSocket frame
     */
    public function encodeWebSocketFrame(string $payload, int $opcode = 1): string
    {
        $payloadLength = strlen($payload);
        $header = '';
        
        // Set FIN bit and opcode (1 for text)
        $header .= chr(0x80 | $opcode);
        
        // Set payload length
        if ($payloadLength <= 125) {
            $header .= chr($payloadLength);
        } elseif ($payloadLength <= 65535) {
            $header .= chr(126) . pack('n', $payloadLength);
        } else {
            $header .= chr(127) . pack('J', $payloadLength);
        }
        
        return $header . $payload;
    }

    /**
     * Send a pong frame in response to a ping
     * 
     * @param resource $client The client socket resource
     * @return void
     */
    public function sendPong($client): void
    {
        $pongFrame = $this->encodeWebSocketFrame('', 10);
        fwrite($client, $pongFrame);
    }

    /**
     * Prepare a message for sending over WebSocket
     * 
     * @param string $event The event name
     * @param array<string, mixed> $data The data to send
     * @return string The encoded WebSocket message
     */
    public function prepareMessage(string $event, array $data): string
    {
        $message = json_encode([
            'event' => $event,
            'data' => $data
        ]);
        
        if ($message === false) {
            $message = json_encode([
                'event' => 'error',
                'data' => ['message' => 'Failed to encode message']
            ]);
            if ($message === false) {
                $message = '{"event":"error","data":{"message":"JSON encoding error"}}';
            }
        }

        return $this->encodeWebSocketFrame($message);
    }

    /**
     * Handle an incoming WebSocket message
     * 
     * @param int $clientId The client ID
     * @param string $payload The message payload
     * @return void
     */
    protected function handleMessage(int $clientId, string $payload): void
    {
        try {
            $message = json_decode($payload, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->server->getLogger()->warning("Invalid JSON received from client: $clientId", [
                    'error' => json_last_error_msg(),
                    'payload' => substr($payload, 0, 100) . (strlen($payload) > 100 ? '...' : '')
                ]);
                return;
            }
            
            if (!is_array($message) || !isset($message['event']) || !is_string($message['event'])) {
                $this->server->getLogger()->warning("Invalid message format from client: $clientId", [
                    'payload' => substr($payload, 0, 100) . (strlen($payload) > 100 ? '...' : '')
                ]);
                return;
            }
            
            $event = $message['event'];
            $data = isset($message['data']) && is_array($message['data']) ? $message['data'] : [];

            $this->server->getRouter()->dispatch($clientId, $event, $data); //@phpstan-ignore-line
        } catch (Throwable $e) {
            $this->server->getLogger()->exception($e, ['clientId' => $clientId, 'context' => 'WebSocketHandler::handleMessage']);
        }
    }
}
