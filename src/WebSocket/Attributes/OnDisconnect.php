<?php

/**
 * OnDisconnect attribute class
 *
 * Attribute for marking methods as WebSocket disconnection event handlers
 * This method will be called automatically when a client disconnects from the WebSocket
 *
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\WebSocket\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class OnDisconnect
{
    /**
     * Constructor
     *
     * OnDisconnect handlers are automatically triggered when a WebSocket connection is lost
     */
    public function __construct() {}
}
