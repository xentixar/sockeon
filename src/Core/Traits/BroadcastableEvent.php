<?php
/**
 * BroadcastableEvent trait
 * 
 * Trait for events that can be broadcasted to multiple clients
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core\Traits;

trait BroadcastableEvent
{
    /**
     * Broadcast this event to multiple clients
     * 
     * @param array  $data      The data to send
     * @param string $namespace Optional namespace to broadcast within
     * @param string $room      Optional room to broadcast to
     * @return void
     */
    public static function broadcastTo(array $data, ?string $namespace = '/', ?string $room = null): void
    {
        self::getServerInstance()->broadcast(self::getEventName(), $data, $namespace, $room);
    }
    
    /**
     * Gets the event name for this event class
     * 
     * @return string The event name
     */
    protected static function getEventName(): string
    {
        $instance = new static();
        return $instance->getName();
    }
}
