<?php
/**
 * EmittableEventInterface
 * 
 * Interface for events that can be emitted to specific clients
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core\Contracts\Events;

interface EmittableEventInterface
{
    /**
     * Emit this event directly to a specific client
     * 
     * @param int $clientId The ID of the client to send to
     * @param array<string, mixed> $data The data to send
     * @return void
     */
    public static function emitTo(int $clientId, array $data): void;
}
