<?php
/**
 * HandlesListening trait
 * 
 * Manages message listening and event loop for WebSocket client
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Traits\Client;

use Sockeon\Sockeon\Exception\Client\ConnectionException;

trait HandlesListening
{
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
                
                $this->handleIncomingData($data);
            }
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

    /**
     * Handle incoming WebSocket data
     *
     * @param string $data Raw data from socket
     * @return void
     */
    protected function handleIncomingData(string $data): void
    {
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
