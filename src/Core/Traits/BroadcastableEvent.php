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

//@phpstan-ignore-next-line
trait BroadcastableEvent
{
    /**
     * Broadcast this event to multiple clients
     *
     * @param array<string, mixed> $data The data to send
     * @param string|null $namespace Optional namespace to broadcast within
     * @param string|null $room Optional room to broadcast to
     * @return void
     */
    public static function broadcastTo(array $data, ?string $namespace = '/', ?string $room = null): void
    {
        self::getServerInstance()->broadcast(self::getEventName(), $data, $namespace, $room); //@phpstan-ignore-line
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
