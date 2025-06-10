<?php
/**
 * Client class
 * 
 * Simplified wrapper for WebSocketClient to provide an easy-to-use
 * interface for connecting to Sockeon WebSocket servers.
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Client;

use Sockeon\Sockeon\Client\Exceptions\ConnectionException;
use Sockeon\Sockeon\Core\ConnectionConfig;
use Sockeon\Sockeon\Core\Security\BroadcastAuthenticator;

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
     * @param string|null $host     WebSocket server host
     * @param int|null    $port     WebSocket server port
     * @param string|null $path     WebSocket endpoint path
     * @param int|null    $timeout  Connection timeout in seconds
     */
    public function __construct(?string $host = null, ?int $port = null, string $path = '/', int $timeout = 10)
    {
        $configHost = $host ?? ConnectionConfig::getServerHost();
        $configPort = $port ?? ConnectionConfig::getServerPort();
        
        $this->client = new WebSocketClient($configHost, $configPort, $path, $timeout);
    }

    /**
     * Connect to the WebSocket server
     * 
     * @param array<string, string> $headers Additional HTTP headers for the WebSocket handshake
     * @return bool True on success
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
        } catch (\Exception $e) {
            error_log($e->getMessage());
            
            if ($this->autoReconnect && $this->reconnectAttempts < $this->maxReconnectAttempts) {
                $this->reconnect();
                
                try {
                    return $this->client->emit($event, $data);
                } catch (\Exception $e) {
                    error_log("Failed to emit event after reconnection: " . $e->getMessage());
                }
            }
            
            return false;
        }
    }

    /**
     * Broadcast an event to other clients via the server
     * 
     * @param string $event Event name
     * @param array<string, mixed> $data Event data
     * @param string $namespace Optional namespace to broadcast to
     * @param string $room Optional room name to broadcast to
     * @return bool True on success
     */
    public function broadcast(string $event, array $data = [], string $namespace = '/', string $room = ''): bool
    {
        $timestamp = time();
        $clientId = spl_object_hash($this);
        
        $broadcastData = [
            'event' => $event,
            'data' => $data,
            '_auth' => [
                'timestamp' => $timestamp,
                'clientId' => $clientId,
                'token' => BroadcastAuthenticator::generateToken($clientId, $timestamp)
            ]
        ];
        
        if ($namespace !== '/') {
            $broadcastData['namespace'] = $namespace;
        }
        
        if ($room !== '') {
            $broadcastData['room'] = $room;
        }
        
        try {
            return $this->client->emit('server:broadcast', $broadcastData);
        } catch (\Exception $e) {
            error_log($e->getMessage());
            
            if ($this->autoReconnect && $this->reconnectAttempts < $this->maxReconnectAttempts) {
                $this->reconnect();
                
                try {
                    return $this->client->emit('server:broadcast', $broadcastData);
                } catch (\Exception $e) {
                    error_log("Failed to broadcast event after reconnection: " . $e->getMessage());
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
        } catch (\Exception $e) {
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
