<?php
/**
 * SocketOn attribute class
 * 
 * Attribute for marking methods as WebSocket event handlers
 * Used to associate controller methods with specific WebSocket events
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\WebSocket\Attributes;

use Attribute;
use Sockeon\Sockeon\Core\Event;

#[Attribute(Attribute::TARGET_METHOD)]
class SocketOn
{
    /**
     * The event name this handler responds to
     * @var string
     */
    public string $event;

    /**
     * Constructor
     * 
     * @param string|Event|string $event  The event name, Event class instance, or Event class string
     * @param array<int, class-string> $middlewares List of middleware classes to apply to this event handler
     */
    public function __construct(string $event, public array $middlewares = [])
    {
        $this->event = Event::resolveEventName($event);
    }
}
