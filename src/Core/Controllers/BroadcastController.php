<?php
/**
 * BroadcastController class
 * 
 * Controller to handle server:broadcast events from clients
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core\Controllers;

use Sockeon\Sockeon\Core\Contracts\SocketController;
use Sockeon\Sockeon\WebSocket\Attributes\SocketOn;
use Sockeon\Sockeon\Core\Security\BroadcastAuthenticator;

class BroadcastController extends SocketController
{
    /**
     * Handle server:broadcast event from clients
     * 
     * @param int $clientId The client ID sending the broadcast request
     * @param array<string, mixed> $data The broadcast data
     * @return bool Success status
     */
    #[SocketOn('server:broadcast')]
    public function handleBroadcast(int $clientId, array $data): bool
    {
        if (!$this->isAuthenticatedBroadcast($data)) {
            $this->server->getLogger()->warning("Unauthorized broadcast attempt", [
                'clientId' => $clientId,
                'ip' => $this->server->getClientInfo($clientId, 'ip') ?? 'unknown'
            ]);
            return false;
        }
        
        if (!isset($data['event']) || !is_string($data['event'])) {
            return false;
        }
        
        $event = $data['event'];
        $eventData = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
        $namespace = isset($data['namespace']) && is_string($data['namespace']) ? $data['namespace'] : '/';
        $room = isset($data['room']) && is_string($data['room']) ? $data['room'] : null;
        
        $this->server->getLogger()->debug("Broadcasting event: {$event}", [
            'from_client' => $clientId,
            'namespace' => $namespace,
            'room' => $room,
        ]);
        
        if ($room !== null) {
            $clients = $this->server->getNamespaceManager()->getClientsInRoom($room, $namespace);
            $clients = array_filter($clients, function($id) use ($clientId) {
                return $id !== $clientId;
            });
            
            foreach ($clients as $targetClientId) {
                $this->server->send($targetClientId, $event, $eventData);
            }
        } else {
            $clients = $this->server->getNamespaceManager()->getClientsInNamespace($namespace);
            $clients = array_filter($clients, function($id) use ($clientId) {
                return $id !== $clientId;
            });
            
            foreach ($clients as $targetClientId) {
                $this->server->send($targetClientId, $event, $eventData);
            }
        }
        
        return true;
    }
    
    /**
     * Verify if the broadcast request is authenticated
     * 
     * @param array<string, mixed> $data The broadcast data
     * @return bool Whether the request is authenticated
     */
    private function isAuthenticatedBroadcast(array $data): bool
    {
        if (!isset($data['_auth']) || !is_array($data['_auth'])) {
            return false;
        }
        
        $auth = $data['_auth'];
        
        if (!isset($auth['timestamp']) || !is_int($auth['timestamp'])) {
            return false;
        }
        
        if (!isset($auth['clientId']) || !is_string($auth['clientId'])) {
            return false;
        }
        
        if (!isset($auth['token']) || !is_string($auth['token'])) {
            return false;
        }
        
        return BroadcastAuthenticator::validateToken(
            $auth['token'],
            $auth['clientId'],
            $auth['timestamp'],
            60
        );
    }
}
