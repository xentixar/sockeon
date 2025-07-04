<?php

namespace Sockeon\Sockeon\Core\Contracts;

interface EventableContract
{
    /**
     * Get the name of the event for broadcasting.
     *
     * @return string
     */
    public function broadcastAs(): string;

    /**
     * Get the data to be broadcast with the event.
     * 
     * @return array<mixed>
     */
    public function broadcastWith(): array;

    /**
     * Get the rooms to broadcast the event to.
     * 
     * @return array<string>|null
     */
    public function broadcastOn(): ?array;

    /**
     * Get the namespace for the broadcast.
     *
     * @return string|null
     */
    public function broadcastNamespace(): ?string;
}
