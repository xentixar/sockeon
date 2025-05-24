<?php
/**
 * SocketController abstract class
 * 
 * Base class for all socket controllers providing access to core server functionalities
 * Provides methods for emitting events, broadcasting messages, and managing rooms
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core\Contracts;

use Sockeon\Sockeon\Core\Server;

abstract class SocketController
{
    /**
     * Instance of the server
     * @var Server
     */
    protected Server $server;

    /**
     * Sets the server instance for this controller
     * 
     * @param Server $server    The server instance
     * @return void
     */
    public function setServer(Server $server): void
    {
        $this->server = $server;
    }

    /**
     * Emits an event to a specific client
     * 
     * @param int       $clientId   The ID of the client to send to
     * @param string    $event      The event name
     * @param array     $data       The data to send
     * @return void
     */
    public function emit(int $clientId, string $event, array $data): void
    {
        $this->server->send($clientId, $event, $data);
    }

    /**
     * Broadcasts an event to multiple clients
     * 
     * @param string    $event      The event name
     * @param array     $data       The data to send
     * @param string    $namespace  Optional namespace to broadcast within
     * @param string    $room       Optional room to broadcast to
     * @return void
     */
    public function broadcast(string $event, array $data, ?string $namespace = null, ?string $room = null): void
    {
        $this->server->broadcast($event, $data, $namespace, $room);
    }
    
    /**
     * Adds a client to a room
     * 
     * @param int       $clientId   The ID of the client to add
     * @param string    $room       The room name
     * @param string    $namespace  The namespace
     * @return void
     */
    public function joinRoom(int $clientId, string $room, string $namespace = '/'): void
    {
        $this->server->joinRoom($clientId, $room, $namespace);
    }
    
    /**
     * Removes a client from a room
     * 
     * @param int       $clientId   The ID of the client to remove
     * @param string    $room       The room name to leave
     * @param string    $namespace  The namespace containing the room
     * @return void
     */
    public function leaveRoom(int $clientId, string $room, string $namespace = '/'): void
    {
        $this->server->leaveRoom($clientId, $room, $namespace);
    }
    
    /**
     * Disconnects a client from the server
     * 
     * Closes the connection and cleans up all client resources
     * 
     * @param int $clientId  The ID of the client to disconnect
     * @return void
     */
    public function disconnectClient(int $clientId): void
    {
        $this->server->disconnectClient($clientId);
    }
}
