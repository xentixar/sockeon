<?php
/**
 * BroadcastableEventInterface
 * 
 * Interface for events that can be broadcasted to multiple clients
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core\Contracts\Events;

interface BroadcastableEventInterface
{
    /**
     * Broadcast this event to multiple clients
     * 
     * @param array  $data      The data to send
     * @param string $namespace Optional namespace to broadcast within
     * @param string $room      Optional room to broadcast to
     * @return void
     */
    public static function broadcastTo(array $data, ?string $namespace = null, ?string $room = null): void;
}
