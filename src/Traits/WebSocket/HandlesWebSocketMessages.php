<?php
/**
 * HandlesWebSocketMessages trait
 * 
 * Manages WebSocket message processing and routing
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Traits\WebSocket;

use Throwable;

trait HandlesWebSocketMessages
{
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
