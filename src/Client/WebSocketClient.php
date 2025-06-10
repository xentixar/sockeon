<?php
/**
 * WebSocketClient class
 * 
 * PHP client for connecting to Sockeon WebSocket server, listening to events,
 * and emitting events.
 * 
 * @package     Sockeon\Sockeon\Client
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Client;

use Sockeon\Sockeon\Client\Exceptions\ConnectionException;
use Sockeon\Sockeon\Client\Exceptions\HandshakeException;
use Sockeon\Sockeon\Client\Exceptions\MessageException;

class WebSocketClient
{
    /**
     * WebSocket server host
     * @var string
     */
    protected string $host;

    /**
     * WebSocket server port
     * @var int
     */
    protected int $port;

    /**
     * WebSocket endpoint path
     * @var string
     */
    protected string $path;

    /**
     * Connection timeout in seconds
     * @var int
     */
    protected int $timeout;

    /**
     * Socket resource
     * @var resource|null
     * @phpstan-var resource|null
     */
    protected $socket = null;

    /**
     * Event listeners organized by event name
     * @var array<string, array<callable>>
     */
    protected array $eventListeners = [];

    /**
     * Flag to check if connection is established
     * @var bool
     */
    protected bool $connected = false;

    /**
     * Constructor
     * 
     * @param string $host     WebSocket server host
     * @param int    $port     WebSocket server port
     * @param string $path     WebSocket endpoint path
     * @param int    $timeout  Connection timeout in seconds
     */
    public function __construct(string $host, int $port, string $path = '/', int $timeout = 10)
    {
        $this->host = $host;
        $this->port = $port;
        $this->path = $path;
        $this->timeout = $timeout;
    }

    /**
     * Connect to the WebSocket server
     *
     * @param array<string, string> $headers Additional HTTP headers for the WebSocket handshake
     * @return bool True on success
     * @throws ConnectionException If connection fails
     * @throws HandshakeException If WebSocket handshake fails
     */
    public function connect(array $headers = []): bool
    {
        $this->disconnect();

        $socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}", 
            $errno, 
            $errstr, 
            $this->timeout,
            STREAM_CLIENT_CONNECT
        );
        
        if ($socket === false) {
            throw new ConnectionException("Could not connect to WebSocket server: " . (is_string($errstr) ? $errstr : 'Unknown error') . " (" . (is_int($errno) ? $errno : 'Unknown code') . ")");
        }
        
        $this->socket = $socket;

        stream_set_blocking($this->socket, false);
        stream_set_timeout($this->socket, $this->timeout);

        $key = base64_encode(openssl_random_pseudo_bytes(16));
        
        $defaultHeaders = [
            'Host' => "{$this->host}:{$this->port}",
            'User-Agent' => 'Sockeon PHP WebSocket Client',
            'Connection' => 'Upgrade',
            'Upgrade' => 'websocket',
            'Sec-WebSocket-Key' => $key,
            'Sec-WebSocket-Version' => '13',
            'Origin' => "http://{$this->host}"
        ];
        
        $headers = array_merge($defaultHeaders, $headers);
        
        $request = "GET {$this->path} HTTP/1.1\r\n";
        
        foreach ($headers as $headerName => $headerValue) {
            $request .= "$headerName: $headerValue\r\n";
        }
        
        $request .= "\r\n";

        $bytesWritten = fwrite($this->socket, $request);
        if ($bytesWritten === false || $bytesWritten < strlen($request)) {
            throw new ConnectionException("Failed to send WebSocket handshake request");
        }

        $response = '';
        $startTime = time();
        
        while (true) {
            if (time() - $startTime > $this->timeout) {
                throw new HandshakeException("Handshake timed out");
            }
            
            $buffer = fread($this->socket, 8192);
            if ($buffer === false) {
                throw new HandshakeException("Failed to read from socket during handshake");
            }
            
            $response .= $buffer;
            
            if (strpos($response, "\r\n\r\n") !== false) {
                break;
            }
            
            usleep(10000);
        }

        if (!preg_match('#Switching Protocols#i', $response)) {
            throw new HandshakeException("WebSocket handshake failed: " . substr($response, 0, 100));
        }

        $expectedKey = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        
        if (!preg_match('#Sec-WebSocket-Accept:\s(.+)\r\n#i', $response, $matches) || trim($matches[1]) !== $expectedKey) {
            throw new HandshakeException("Invalid Sec-WebSocket-Accept value");
        }

        $this->connected = true;
        return true;
    }

    /**
     * Disconnect from the WebSocket server
     *
     * @return bool True on success
     */
    public function disconnect(): bool
    {
        if ($this->socket !== null) {
            if ($this->connected) {
                $this->sendCloseFrame();
            }
            
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
            $this->socket = null;
            $this->connected = false;
        }
        
        return true;
    }

    /**
     * Register an event listener
     *
     * @param string $event    Event name to listen for
     * @param callable $callback Function to call when event is received
     * @return $this
     */
    public function on(string $event, callable $callback): self
    {
        if (!isset($this->eventListeners[$event])) {
            $this->eventListeners[$event] = [];
        }
        
        $this->eventListeners[$event][] = $callback;
        
        return $this;
    }

    /**
     * Emit an event to the server
     *
     * @param string $event Event name
     * @param array<string, mixed> $data Event data
     * @return bool True on success
     * @throws ConnectionException If not connected
     * @throws MessageException If sending fails
     */
    public function emit(string $event, array $data = []): bool
    {
        if (!$this->connected || $this->socket === null) {
            throw new ConnectionException("Not connected to WebSocket server");
        }

        $message = json_encode([
            'event' => $event,
            'data' => $data
        ]);
        
        if ($message === false) {
            throw new MessageException("Failed to encode message");
        }
        
        $frame = $this->createWebSocketFrame($message);
        
        if (!is_resource($this->socket)) {
            throw new ConnectionException("Invalid socket resource");
        }
        
        $bytesWritten = fwrite($this->socket, $frame);
        
        if ($bytesWritten === false || $bytesWritten < strlen($frame)) {
            throw new MessageException("Failed to send message");
        }
        
        return true;
    }

    /**
     * Listen for incoming messages from the server
     *
     * @param int $timeout Timeout in seconds, 0 for non-blocking
     * @return void
     * @throws ConnectionException If not connected
     */
    public function listen(int $timeout = 0): void
    {
        if (!$this->connected || $this->socket === null) {
            throw new ConnectionException("Not connected to WebSocket server");
        }

        if (is_resource($this->socket)) {
            stream_set_timeout($this->socket, $timeout);
        }
        
        $read = is_resource($this->socket) ? [$this->socket] : [];
        $write = null;
        $except = null;
        
        if (stream_select($read, $write, $except, $timeout) > 0) {
            foreach ($read as $socket) {
                if (!is_resource($socket)) {
                    $this->disconnect();
                    return;
                }
                
                $data = fread($socket, 8192);
                
                if ($data === false || strlen($data) === 0) {
                    $this->disconnect();
                    return;
                }
                
                $frames = $this->decodeWebSocketFrames($data);
                
                foreach ($frames as $frame) {
                    if ($frame['opcode'] === 8) {
                        $this->disconnect();
                        return;
                    } elseif ($frame['opcode'] === 9) {
                        $pongFrame = $this->createWebSocketFrame('', 10);
                        if (is_resource($this->socket)) {
                            fwrite($this->socket, $pongFrame);
                        }
                        continue;
                    } elseif ($frame['opcode'] === 10) {
                        continue;
                    }
                    
                    if (($frame['opcode'] === 1 || $frame['opcode'] === 2) && isset($frame['payload']) && is_string($frame['payload'])) {
                        $this->processMessage($frame['payload']);
                    }
                }
            }
        }
    }

    /**
     * Process a message and trigger appropriate event listeners
     *
     * @param string $payload JSON message payload
     * @return void
     */
    protected function processMessage(string $payload): void
    {
        $message = json_decode($payload, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($message)) {
            return;
        }
        
        if (!isset($message['event']) || !is_string($message['event'])) {
            return;
        }
        
        $event = $message['event'];
        $data = isset($message['data']) && is_array($message['data']) ? $message['data'] : [];
        
        if (isset($this->eventListeners[$event])) {
            foreach ($this->eventListeners[$event] as $listener) {
                call_user_func($listener, $data);
            }
        }
        
        if (isset($this->eventListeners['message'])) {
            foreach ($this->eventListeners['message'] as $listener) {
                call_user_func($listener, $event, $data);
            }
        }
    }

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

    /**
     * Check if the client is connected to the server
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->connected && $this->socket !== null;
    }
    
    /**
     * Start event loop to continuously listen for messages
     *
     * @param callable|null $callback Optional callback to be called on each iteration
     * @param int $checkInterval How often to check for messages in milliseconds
     * @return void
     */
    public function run(?callable $callback = null, int $checkInterval = 50): void
    {
        while ($this->isConnected()) {
            $this->listen(0);
            
            if ($callback !== null) {
                $result = call_user_func($callback);
                if ($result === false) {
                    break;
                }
            }
            
            usleep($checkInterval * 1000);
        }
    }
}
