<?php
/**
 * HandlesFrames trait
 * 
 * Manages WebSocket frame encoding and decoding for client
 * 
 * @package     Sockeon\Sockeon\Traits\Client
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Traits\Client;

trait HandlesFrames
{
    /**
     * Create a WebSocket frame
     *
     * @param string $payload Data to be sent
     * @param int $opcode Frame type (1=text, 8=close, 9=ping, 10=pong)
     * @param bool $masked Whether to mask the frame (clients should always mask)
     * @return string Binary frame data
     */
    protected function createWebSocketFrame(string $payload, int $opcode = 1, bool $masked = true): string
    {
        $length = strlen($payload);
        $mask = '';
        $maskKey = '';
        
        $frame = chr(0x80 | $opcode);
        
        if ($length <= 125) {
            $frame .= chr($length | ($masked ? 0x80 : 0));
        } elseif ($length <= 65535) {
            $frame .= chr(126 | ($masked ? 0x80 : 0));
            $frame .= pack('n', $length);
        } else {
            $frame .= chr(127 | ($masked ? 0x80 : 0));
            $frame .= pack('J', $length);
        }
        
        if ($masked) {
            $maskKey = openssl_random_pseudo_bytes(4);
            $mask = $maskKey;
            $frame .= $maskKey;
            
            $maskedPayload = '';
            for ($i = 0; $i < $length; $i++) {
                $maskedPayload .= $payload[$i] ^ $maskKey[$i % 4];
            }
            $payload = $maskedPayload;
        }
        
        $frame .= $payload;
        
        return $frame;
    }

    /**
     * Decode WebSocket frames from raw data
     *
     * @param string $data Raw WebSocket frame data
     * @return array<int, array<string, mixed>> Array of decoded frames
     */
    protected function decodeWebSocketFrames(string $data): array
    {
        $frames = [];
        
        while (strlen($data) > 0) {
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
            
            if ($payloadLength == 126) {
                if (strlen($data) < 4) {
                    break;
                }
                $unpacked = unpack('n', substr($data, 2, 2));
                if (!$unpacked || !isset($unpacked[1])) {
                    break;
                }
                $payloadLength = (int)$unpacked[1]; //@phpstan-ignore-line
                $offset += 2;
            }
            elseif ($payloadLength == 127) {
                if (strlen($data) < 10) {
                    break;
                }
                $unpacked = unpack('J', substr($data, 2, 8));
                if (!$unpacked || !isset($unpacked[1])) {
                    break;
                }
                if (is_int($unpacked[1])) {
                    $payloadLength = $unpacked[1];
                } else {
                    $payloadLength = (int)$unpacked[1]; //@phpstan-ignore-line
                }
                $offset = $offset + 8;
            }
            
            $frameLength = $offset;
            if ($masked) {
                $frameLength += 4;
            }
            $frameLength += $payloadLength;

            if (strlen($data) < $frameLength) {
                break;
            }

            if ($masked) {
                $maskKey = substr($data, $offset, 4);
                $offset += 4;

                $payload = substr($data, $offset, (int)$payloadLength);
                $unmaskedPayload = '';
                
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
                $payload = substr($data, $offset, (int)$payloadLength);
                $frames[] = [
                    'fin' => $fin,
                    'opcode' => $opcode,
                    'masked' => $masked,
                    'payload' => $payload
                ];
            }
            
            $data = substr($data, $frameLength);
        }
        
        return $frames;
    }

    /**
     * Send a WebSocket close frame to the server
     *
     * @param int $code WebSocket close status code
     * @param string $reason Reason for closing
     * @return void
     */
    protected function sendCloseFrame(int $code = 1000, string $reason = ''): void
    {
        if ($this->socket === null || !$this->connected) {
            return;
        }
        
        $payload = pack('n', $code) . $reason;
        $frame = $this->createWebSocketFrame($payload, 8);
        
        if (is_resource($this->socket)) {
            fwrite($this->socket, $frame);
        }
    }
}
