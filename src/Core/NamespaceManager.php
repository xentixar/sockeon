<?php
/**
 * NamespaceManager class
 * 
 * Manages namespaces and rooms for WebSocket connections
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core;

class NamespaceManager
{
    /**
     * The namespaces and clients within them
     * @var array<string, array<int, int>>
     */
    protected array $namespaces = [
        '/' => []
    ];
    
    /**
     * Room definitions
     * @var array<string, array<string, array<int, int>>>
     */
    protected array $rooms = [];
    
    /**
     * Map of which clients belong to which namespaces
     * @var array<int, string>
     */
    protected array $clientNamespaces = [];
    
    /**
     * Map of which clients belong to which rooms
     * @var array<int, array<string, string>>
     */
    protected array $clientRooms = [];
    
    /**
     * Add a client to a namespace
     * 
     * @param int $clientId The client ID to add
     * @param string $namespace The namespace to join
     * @return void
     */
    public function joinNamespace(int $clientId, string $namespace = '/'): void
    {
        if (!isset($this->namespaces[$namespace])) {
            $this->namespaces[$namespace] = [];
        }
        
        $this->namespaces[$namespace][$clientId] = $clientId;
        $this->clientNamespaces[$clientId] = $namespace;
    }
    
    /**
     * Remove a client from its namespace
     * 
     * @param int $clientId The client ID to remove
     * @return void
     */
    public function leaveNamespace(int $clientId): void
    {
        $namespace = $this->clientNamespaces[$clientId] ?? '/';
        
        unset($this->namespaces[$namespace][$clientId]);
        unset($this->clientNamespaces[$clientId]);
        
        $this->leaveAllRooms($clientId);
    }
    
    /**
     * Add a client to a room within a namespace
     * 
     * @param int $clientId The client ID to add
     * @param string $room The room name to join
     * @param string $namespace The namespace containing the room
     * @return void
     */
    public function joinRoom(int $clientId, string $room, string $namespace = '/'): void
    {
        if (!isset($this->rooms[$namespace])) {
            $this->rooms[$namespace] = [];
        }
        
        if (!isset($this->rooms[$namespace][$room])) {
            $this->rooms[$namespace][$room] = [];
        }
        
        $this->rooms[$namespace][$room][$clientId] = $clientId;
        
        if (!isset($this->clientRooms[$clientId])) {
            $this->clientRooms[$clientId] = [];
        }
        
        $this->clientRooms[$clientId][$room] = $namespace;
    }
    
    /**
     * Remove a client from a room
     * 
     * @param int $clientId The client ID to remove
     * @param string $room The room name to leave
     * @param string $namespace The namespace containing the room
     * @return void
     */
    public function leaveRoom(int $clientId, string $room, string $namespace = '/'): void
    {
        if (isset($this->rooms[$namespace][$room][$clientId])) {
            unset($this->rooms[$namespace][$room][$clientId]);
            unset($this->clientRooms[$clientId][$room]);
        }
    }
    
    /**
     * Remove a client from all rooms
     * 
     * @param int $clientId The client ID to remove from all rooms
     * @return void
     */
    public function leaveAllRooms(int $clientId): void
    {
        if (!isset($this->clientRooms[$clientId])) {
            return;
        }
        
        $clientRooms = $this->clientRooms[$clientId];
        foreach ($clientRooms as $room => $namespace) {
            $this->leaveRoom($clientId, $room, $namespace);
        }
        
        unset($this->clientRooms[$clientId]);
    }
    
    /**
     * Get all clients in a namespace
     * 
     * @param string $namespace The namespace to query
     * @return array<int, int> Array of client IDs
     */
    public function getClientsInNamespace(string $namespace = '/'): array
    {
        return $this->namespaces[$namespace] ?? [];
    }
    
    /**
     * Get all clients in a room
     * 
     * @param string $room The room name
     * @param string $namespace The namespace containing the room
     * @return array<int, int> Array of client IDs
     */
    public function getClientsInRoom(string $room, string $namespace = '/'): array
    {
        return $this->rooms[$namespace][$room] ?? [];
    }
    
    /**
     * Get all rooms in a namespace
     * 
     * @param string $namespace The namespace to query
     * @return array<int, string> Array of room names
     */
    public function getRooms(string $namespace = '/'): array
    {
        return array_keys($this->rooms[$namespace] ?? []);
    }
    
    /**
     * Get the namespace a client belongs to
     * 
     * @param int $clientId The client ID to query
     * @return string The namespace the client belongs to
     */
    public function getClientNamespace(int $clientId): string
    {
        return $this->clientNamespaces[$clientId] ?? '/';
    }
    
    /**
     * Get all rooms a client belongs to
     * 
     * @param int $clientId The client ID to query
     * @return array<int, string> Array of room names the client belongs to
     */
    public function getClientRooms(int $clientId): array
    {
        return array_keys($this->clientRooms[$clientId] ?? []);
    }
    
    /**
     * Clean up all references to a client
     * 
     * Removes the client from its namespace and all rooms.
     * Use this when a client disconnects.
     * 
     * @param int $clientId The client ID to clean up
     * @return void
     */
    public function cleanup(int $clientId): void
    {
        $this->leaveNamespace($clientId);
    }
}
