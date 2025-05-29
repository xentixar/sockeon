<?php
/**
 * EmittableEvent trait
 * 
 * Trait for events that can be emitted to specific clients
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core\Traits;

//@phpstan-ignore-next-line
trait EmittableEvent
{
    /**
     * Emit this event directly to a specific client
     * 
     * @param int $clientId The ID of the client to send to
     * @param array<string, mixed> $data     The data to send
     * @return void
     */
    public static function emitTo(int $clientId, array $data): void
    {
        self::getServerInstance()->send($clientId, self::getEventName(), $data); //@phpstan-ignore-line
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
