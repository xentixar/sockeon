<?php

namespace Sockeon\Sockeon\Traits\Server;

use Throwable;

trait HandlesSendBroadcast
{
    /**
     * Send a WebSocket message to a specific client
     * 
     * @param int $clientId The client ID to send to
     * @param string $event Event name
     * @param array<string, mixed> $data Data to send
     * @return void
     */
    public function send(int $clientId, string $event, array $data): void
    {
        if (isset($this->clients[$clientId]) && ($this->clientTypes[$clientId] ?? '') === 'ws' && is_resource($this->clients[$clientId])) {
            try {
                $frame = $this->wsHandler->prepareMessage($event, $data);
                $result = @fwrite($this->clients[$clientId], $frame);
                
                if ($result === false) {
                    // Connection lost, clean up
                    $this->disconnectClient($clientId);
                }
            } catch (Throwable $e) {
                // Handle any errors and disconnect client
                $this->disconnectClient($clientId);
            }
        }
    }

    /**
     * Send raw message data to a specific client
     * 
     * @param int $clientId The client ID to send to
     * @param string $message Raw message data
     * @return void
     */
    public function sendToClient(int $clientId, string $message): void
    {
        if (isset($this->clients[$clientId]) && ($this->clientTypes[$clientId] ?? '') === 'ws' && is_resource($this->clients[$clientId])) {
            try {
                $frame = $this->wsHandler->encodeWebSocketFrame($message, 1);
                $result = @fwrite($this->clients[$clientId], $frame);
                
                if ($result === false) {
                    // Connection lost, clean up
                    $this->disconnectClient($clientId);
                }
            } catch (Throwable $e) {
                // Handle any errors and disconnect client
                $this->disconnectClient($clientId);
            }
        }
    }

    /**
     * Broadcast a WebSocket message to multiple clients, optionally filtered by namespace and room
     * 
     * @param string $event Event name
     * @param array<string, mixed> $data Data to broadcast
     * @param string|null $namespace Optional namespace filter
     * @param string|null $room Optional room filter
     * @return void
     */
    public function broadcast(string $event, array $data, ?string $namespace = null, ?string $room = null): void
    {
        $frame = $this->wsHandler->prepareMessage($event, $data);

        if ($room !== null && $namespace !== null) {
            $clients = $this->namespaceManager->getClientsInRoom($room, $namespace);
        } elseif ($namespace !== null) {
            $clients = $this->namespaceManager->getClientsInNamespace($namespace);
        } else {
            $clients = array_keys($this->clients);
        }

        $disconnectedClients = [];
        
        foreach ($clients as $clientId) {
            if (isset($this->clients[$clientId]) && ($this->clientTypes[$clientId] ?? '') === 'ws' && is_resource($this->clients[$clientId])) {
                try {
                    $result = @fwrite($this->clients[$clientId], $frame);
                    
                    if ($result === false) {
                        $disconnectedClients[] = $clientId;
                    }
                } catch (Throwable $e) {
                    $disconnectedClients[] = $clientId;
                }
            }
        }
        
        // Clean up disconnected clients after broadcast
        foreach ($disconnectedClients as $clientId) {
            $this->disconnectClient($clientId);
        }
    }
}
