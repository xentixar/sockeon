<?php
/**
 * WebSocketHandler class
 * 
 * Handles WebSocket protocol implementation, connections and message framing
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\WebSocket;

use Sockeon\Sockeon\Core\Server;

class WebSocketHandler
{
    /**
     * Reference to the server instance
     * @var Server
     */
    protected $server;
    
    /**
     * Tracks completed handshakes by client ID
     * @var array
     */
    protected array $handshakes = [];

    /**
     * Constructor
     * 
     * @param Server $server  The server instance
     */
    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    /**
     * Handle an incoming WebSocket message
     * 
     * @param int       $clientId  The client identifier
     * @param resource  $client    The client socket resource
     * @param string    $data      The raw data received from the client
     * @return bool                Whether the client should remain connected
     */
    public function handle(int $clientId, $client, $data): bool
    {
        // Check if this is a new connection needing handshake
        if (!isset($this->handshakes[$clientId])) {
            return $this->performHandshake($clientId, $client, $data);
        }

        // Handle WebSocket frames
        $frames = $this->decodeWebSocketFrame($data);
        if (empty($frames)) {
            return true;
        }

        foreach ($frames as $frame) {
            // Handle ping/pong for keepalive
            if ($frame['opcode'] == 9) {
                // Ping received, send pong
                $this->sendPong($client);
                continue;
            }

            // Check for connection close
            if ($frame['opcode'] == 8) {
                return false; // Connection should be closed
            }

            // Handle regular text message
            if ($frame['opcode'] == 1 && isset($frame['payload'])) {
                $message = json_decode($frame['payload'], true);
                if (isset($message['event'], $message['data'])) {
                    $this->server->getRouter()->dispatch($clientId, $message['event'], $message['data']);
                }
            }
        }

        return true;
    }

    /**
     * Perform WebSocket handshake with client
     * 
     * @param int       $clientId  The client identifier
     * @param resource  $client    The client socket resource
     * @param string    $data      The HTTP handshake request
     * @return bool                Whether the handshake was successful
     */
    protected function performHandshake(int $clientId, $client, string $data): bool
    {
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
     * @param string $data  The raw WebSocket frame data
     * @return array        An array of parsed WebSocket frames
     */
    protected function decodeWebSocketFrame(string $data): array
    {
        $frames = [];
        
        while (strlen($data) > 0) {
            $firstByte = ord($data[0]);
            $secondByte = ord($data[1]);
            
            $fin = ($firstByte & 0x80) == 0x80;
            $opcode = $firstByte & 0x0F;
            $masked = ($secondByte & 0x80) == 0x80;
            $payloadLength = $secondByte & 0x7F;
            
            $offset = 2;
            
            if ($payloadLength == 126) {
                $payloadLength = unpack('n', substr($data, 2, 2))[1];
                $offset += 2;
            } elseif ($payloadLength == 127) {
                $payloadLength = unpack('J', substr($data, 2, 8))[1];
                $offset += 8;
            }
            
            if ($masked) {
                $maskKey = substr($data, $offset, 4);
                $offset += 4;
                
                $payload = substr($data, $offset, $payloadLength);
                $unmaskedPayload = '';
                
                for ($i = 0; $i < strlen($payload); $i++) {
                    $unmaskedPayload .= $payload[$i] ^ $maskKey[$i % 4];
                }
                
                $frames[] = [
                    'fin' => $fin,
                    'opcode' => $opcode,
                    'masked' => $masked,
                    'payload' => $unmaskedPayload
                ];
            } else {
                $payload = substr($data, $offset, $payloadLength);
                $frames[] = [
                    'fin' => $fin,
                    'opcode' => $opcode,
                    'masked' => $masked,
                    'payload' => $payload
                ];
            }
            
            $data = substr($data, $offset + $payloadLength);
        }
        
        return $frames;
    }

    /**
     * Encode a message into a WebSocket frame
     * 
     * @param string $payload  The payload to encode
     * @param int    $opcode   The WebSocket opcode (1=text, 8=close, 9=ping, 10=pong)
     * @return string          The encoded WebSocket frame
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
     * @param resource $client  The client socket resource
     * @return void
     */
    public function sendPong($client): void
    {
        $pongFrame = $this->encodeWebSocketFrame('', 10); // Opcode 10 for pong
        fwrite($client, $pongFrame);
    }

    /**
     * Prepare a message for sending over WebSocket
     * 
     * @param string $event  The event name
     * @param array  $data   The data to send
     * @return string        The encoded WebSocket message
     */
    public function prepareMessage(string $event, array $data): string
    {
        $message = json_encode([
            'event' => $event,
            'data' => $data
        ]);
        
        return $this->encodeWebSocketFrame($message);
    }
}
