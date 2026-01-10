<?php

/**
 * SystemRoomController class
 *
 * Built-in system controller that handles room join/leave events from clients.
 * This controller is automatically registered by default but can be disabled
 * or overridden by user implementations.
 *
 * Handles the following events:
 * - join_room: Add client to a room
 * - leave_room: Remove client from a room
 *
 * Users can override these handlers by registering their own controller
 * with the same event names to add custom logic (authentication, validation, etc.)
 *
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Controllers;

use Sockeon\Sockeon\WebSocket\Attributes\SocketOn;

class SystemRoomController extends SocketController
{
    /**
     * Handle client request to join a room
     *
     * Expected data format:
     * {
     *   "room": "room-name",
     *   "namespace": "/namespace" (optional, defaults to '/')
     * }
     *
     * Override this method in your own controller to add custom logic:
     * - Authentication checks
     * - Room capacity limits
     * - Permission validation
     * - Custom room naming rules
     * - Logging/analytics
     *
     * @param string $clientId The client ID requesting to join
     * @param array<string, mixed> $data The event data containing room and namespace
     * @return bool True if successful, false otherwise
     */
    #[SocketOn('join_room')]
    public function onJoinRoom(string $clientId, array $data): bool
    {
        // Validate required fields
        if (!isset($data['room']) || !is_string($data['room']) || empty($data['room'])) {
            $this->emit($clientId, 'error', [
                'message' => 'Invalid room name',
                'event' => 'join_room',
            ]);
            return false;
        }

        $room = $data['room'];
        $namespace = $data['namespace'] ?? '/';

        // Validate namespace format
        if (!is_string($namespace) || empty($namespace)) {
            $this->emit($clientId, 'error', [
                'message' => 'Invalid namespace',
                'event' => 'join_room',
            ]);
            return false;
        }

        try {
            // Add client to the room
            $this->joinRoom($clientId, $room, $namespace);

            // Send confirmation to the client
            $this->emit($clientId, 'room_joined', [
                'room' => $room,
                'namespace' => $namespace,
                'timestamp' => time(),
            ]);

            $this->getLogger()->debug("Client $clientId joined room: $room in namespace: $namespace");

            return true;
        } catch (\Throwable $e) {
            $this->getLogger()->error("Failed to join room for client $clientId: " . $e->getMessage());

            $this->emit($clientId, 'error', [
                'message' => 'Failed to join room',
                'event' => 'join_room',
            ]);

            return false;
        }
    }

    /**
     * Handle client request to leave a room
     *
     * Expected data format:
     * {
     *   "room": "room-name",
     *   "namespace": "/namespace" (optional, defaults to '/')
     * }
     *
     * Override this method in your own controller to add custom logic:
     * - Cleanup operations
     * - Logging/analytics
     * - Notify other room members
     *
     * @param string $clientId The client ID requesting to leave
     * @param array<string, mixed> $data The event data containing room and namespace
     * @return bool True if successful, false otherwise
     */
    #[SocketOn('leave_room')]
    public function onLeaveRoom(string $clientId, array $data): bool
    {
        // Validate required fields
        if (!isset($data['room']) || !is_string($data['room']) || empty($data['room'])) {
            $this->emit($clientId, 'error', [
                'message' => 'Invalid room name',
                'event' => 'leave_room',
            ]);
            return false;
        }

        $room = $data['room'];
        $namespace = $data['namespace'] ?? '/';

        // Validate namespace format
        if (!is_string($namespace) || empty($namespace)) {
            $this->emit($clientId, 'error', [
                'message' => 'Invalid namespace',
                'event' => 'leave_room',
            ]);
            return false;
        }

        try {
            // Remove client from the room
            $this->leaveRoom($clientId, $room, $namespace);

            // Send confirmation to the client
            $this->emit($clientId, 'room_left', [
                'room' => $room,
                'namespace' => $namespace,
                'timestamp' => time(),
            ]);

            $this->getLogger()->debug("Client $clientId left room: $room in namespace: $namespace");

            return true;
        } catch (\Throwable $e) {
            $this->getLogger()->error("Failed to leave room for client $clientId: " . $e->getMessage());

            $this->emit($clientId, 'error', [
                'message' => 'Failed to leave room',
                'event' => 'leave_room',
            ]);

            return false;
        }
    }
}

