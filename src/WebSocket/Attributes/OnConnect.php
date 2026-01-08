<?php

/**
 * OnConnect attribute class
 *
 * Attribute for marking methods as WebSocket connection event handlers
 * This method will be called automatically when a client establishes a WebSocket connection
 *
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\WebSocket\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class OnConnect
{
    /**
     * Constructor
     *
     * OnConnect handlers are automatically triggered when a WebSocket connection is established
     */
    public function __construct() {}
}
