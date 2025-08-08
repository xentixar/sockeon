<?php
/**
 * HandlesEvents trait
 * 
 * Manages event listeners and event emission for WebSocket client
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Traits\Client;

use Sockeon\Sockeon\Exception\Client\ConnectionException;
use Sockeon\Sockeon\Exception\Client\MessageException;

trait HandlesEvents
{
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
}
