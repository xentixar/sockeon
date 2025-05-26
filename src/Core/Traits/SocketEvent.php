<?php
/**
 * SocketEvent trait
 * 
 * Combined trait for events that can be both emitted and broadcasted
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core\Traits;

trait SocketEvent
{
    use EmittableEvent, BroadcastableEvent {
        EmittableEvent::getEventName insteadof BroadcastableEvent;
    }
}
