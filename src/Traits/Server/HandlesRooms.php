<?php

namespace Sockeon\Sockeon\Traits\Server;

trait HandlesRooms
{
    /**
     * Add a client to a room within a namespace
     *
     * @param string $clientId
     * @param string $room
     * @param string $namespace
     * @return void
     */
    public function joinRoom(string $clientId, string $room, string $namespace = '/'): void
    {
        $this->namespaceManager->joinRoom($clientId, $room, $namespace);
    }

    /**
     * Remove a client from a room within a namespace
     *
     * @param string $clientId
     * @param string $room
     * @param string $namespace
     * @return void
     */
    public function leaveRoom(string $clientId, string $room, string $namespace = '/'): void
    {
        $this->namespaceManager->leaveRoom($clientId, $room, $namespace);
    }
}
