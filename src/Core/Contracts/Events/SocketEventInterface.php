<?php
/**
 * SocketEventInterface
 * 
 * Combined interface for events that can be both emitted and broadcasted
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core\Contracts\Events;

interface SocketEventInterface extends EmittableEventInterface, BroadcastableEventInterface
{
    // Combined interface
}
