<?php
/**
 * SocketOn attribute class
 * 
 * Attribute for marking methods as WebSocket event handlers
 * Used to associate controller methods with specific WebSocket events
 * 
 * @package     Xentixar\Socklet
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Xentixar\Socklet\WebSocket\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class SocketOn
{
    /**
     * Constructor
     * 
     * @param string $event  The event name this handler responds to
     */
    public function __construct(public string $event) {}
}
