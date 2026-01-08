<?php

/**
 * WebSocketClient class
 *
 * PHP client for connecting to Sockeon WebSocket server, listening to events,
 * and emitting events.
 *
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Connection;

use Sockeon\Sockeon\Exception\Client\ConnectionException;
use Sockeon\Sockeon\Exception\Client\HandshakeException;
use Sockeon\Sockeon\Exception\Client\MessageException;
use Sockeon\Sockeon\Traits\Client\HandlesConnection;
use Sockeon\Sockeon\Traits\Client\HandlesEvents;
use Sockeon\Sockeon\Traits\Client\HandlesFrames;
use Sockeon\Sockeon\Traits\Client\HandlesListening;

class WebSocketClient
{
    use HandlesConnection;
    use HandlesEvents;
    use HandlesFrames;
    use HandlesListening;
    /**
     * WebSocket server host
     * @var string
     */
    protected string $host;

    /**
     * WebSocket server port
     * @var int
     */
    protected int $port;

    /**
     * WebSocket endpoint path
     * @var string
     */
    protected string $path;

    /**
     * Connection timeout in seconds
     * @var int
     */
    protected int $timeout;

    /**
     * Socket resource
     * @var resource|null
     * @phpstan-var resource|null
     */
    protected $socket = null;

    /**
     * Event listeners organized by event name
     * @var array<string, array<callable>>
     */
    protected array $eventListeners = [];

    /**
     * Flag to check if connection is established
     * @var bool
     */
    protected bool $connected = false;

    /**
     * Constructor
     *
     * @param string $host     WebSocket server host
     * @param int    $port     WebSocket server port
     * @param string $path     WebSocket endpoint path
     * @param int    $timeout  Connection timeout in seconds
     */
    public function __construct(string $host, int $port, string $path = '/', int $timeout = 10)
    {
        $this->host = $host;
        $this->port = $port;
        $this->path = $path;
        $this->timeout = $timeout;
    }
}
