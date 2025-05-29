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

#[Attribute(Attribute::TARGET_METHOD)]
class SocketOn
{
    /**
     * Constructor
     * 
     * @param string $event The event name this handler responds to
     */
    public function __construct(public string $event) {}
}
