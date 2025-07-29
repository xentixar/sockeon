<?php
/**
 * HandlesWebSocketFrames trait
 * 
 * Manages WebSocket frame encoding and decoding for server
 * 
 * @package     Sockeon\Sockeon\Traits\WebSocket
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Traits\WebSocket;

trait HandlesWebSocketFrames
{
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
}
