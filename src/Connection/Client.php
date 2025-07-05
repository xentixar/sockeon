<?php
/**
 * Client class
 * 
 * Simplified wrapper for WebSocketClient to provide an easy-to-use
 * interface for connecting to Sockeon WebSocket servers.
 * 
 * @package     Sockeon\Sockeon\Client
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Connection;

use Exception;
use Sockeon\Sockeon\Exception\Client\ConnectionException;
use Sockeon\Sockeon\Exception\Client\HandshakeException;

class Client
{
    /**
     * WebSocket client instance
     * @var WebSocketClient
     */
    protected WebSocketClient $client;

    /**
     * Whether client is in auto-reconnect mode
     * @var bool
     */
    protected bool $autoReconnect = false;

    /**
     * Reconnection attempts
     * @var int
     */
    protected int $reconnectAttempts = 0;

    /**
     * Maximum reconnection attempts
     * @var int
     */
    protected int $maxReconnectAttempts = 5;

    /**
     * Reconnection delay in seconds
     * @var int
     */
    protected int $reconnectDelay = 3;

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
        $this->client = new WebSocketClient($host, $port, $path, $timeout);
    }

    /**
     * Connect to the WebSocket server
     *
     * @param array<string, string> $headers Additional HTTP headers for the WebSocket handshake
     * @return bool True on success
     * @throws HandshakeException
     */
    public function connect(array $headers = []): bool
    {
        try {
            return $this->client->connect($headers);
        } catch (ConnectionException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    /**
     * Disconnect from the WebSocket server
     * 
     * @return bool True on success
     */
    public function disconnect(): bool
    {
        return $this->client->disconnect();
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
        $this->client->on($event, $callback);
        return $this;
    }

    /**
     * Emit an event to the server
     * 
     * @param string $event Event name
     * @param array<string, mixed> $data Event data
     * @return bool True on success
     */
    public function emit(string $event, array $data = []): bool
    {
        try {
            return $this->client->emit($event, $data);
        } catch (Exception $e) {
            error_log($e->getMessage());
            
            if ($this->autoReconnect && $this->reconnectAttempts < $this->maxReconnectAttempts) {
                $this->reconnect();
                
                try {
                    return $this->client->emit($event, $data);
                } catch (Exception $e) {
                    error_log("Failed to emit event after reconnection: " . $e->getMessage());
                }
            }
            
            return false;
        }
    }

    /**
     * Start event loop to continuously listen for messages
     *
     * @param callable|null $callback Optional callback to be called on each iteration
     * @param int $checkInterval How often to check for messages in milliseconds
     * @return void
     * @throws ConnectionException
     */
    public function run(?callable $callback = null, int $checkInterval = 50): void
    {
        $this->client->run(function() use ($callback) {
            if ($callback !== null) {
                return call_user_func($callback);
            }
            return true;
        }, $checkInterval);

        if ($this->autoReconnect && $this->reconnectAttempts < $this->maxReconnectAttempts) {
            $this->reconnect();
            
            $this->run($callback, $checkInterval);
        }
    }

    /**
     * Enable or disable automatic reconnection
     * 
     * @param bool $enabled Whether to enable auto-reconnect
     * @param int $maxAttempts Maximum number of reconnection attempts
     * @param int $delay Delay between reconnection attempts in seconds
     * @return $this
     */
    public function setAutoReconnect(bool $enabled, int $maxAttempts = 5, int $delay = 3): self
    {
        $this->autoReconnect = $enabled;
        $this->maxReconnectAttempts = $maxAttempts;
        $this->reconnectDelay = $delay;
        return $this;
    }

    /**
     * Check if client is connected
     * 
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->client->isConnected();
    }

    /**
     * Attempt to reconnect to the WebSocket server
     * 
     * @return bool True if reconnection was successful
     */
    protected function reconnect(): bool
    {
        $this->reconnectAttempts++;
        
        sleep($this->reconnectDelay);
        
        try {
            return $this->client->connect();
        } catch (Exception $e) {
            error_log("Reconnection attempt {$this->reconnectAttempts} failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reset reconnection attempts counter
     * 
     * @return $this
     */
    public function resetReconnectAttempts(): self
    {
        $this->reconnectAttempts = 0;
        return $this;
    }
}
