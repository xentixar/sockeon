<?php
/**
 * Example Sockeon namespace application
 * 
 * This file demonstrates how to use namespaces and rooms in the Sockeon library
 * for role-based WebSocket message broadcasting
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Sockeon\Sockeon\Core\Contracts\SocketController;
use Sockeon\Sockeon\Core\Server;
use Sockeon\Sockeon\WebSocket\Attributes\SocketOn;

class TestController extends SocketController
{
    /**
     * Handle test.event WebSocket event
     * 
     * Processes incoming messages and broadcasts them to role-specific rooms
     * 
     * @param string $clientId   The ID of the client sending the message
     * @param array  $data       Message data containing:
     *                          - role: (string) User role ('admin' or other)
     *                          - message: (string) Optional message content
     * @return void
     */
    #[SocketOn('test.event')]
    public function sendMessage($clientId, $data)
    {
        if ($data['role'] == 'admin') {
            $this->joinRoom($clientId, 'test.room', '/admin');
            $this->broadcast('test.event', [
                'message' => $data['message'] ?? 'Hello from server',
                'time' => date('H:i:s')
            ], '/admin', 'test.room');
        } else {
            $this->joinRoom($clientId, 'test.room', '/user');
            $this->broadcast('test.event', [
                'message' => $data['message'] ?? 'Hello from server',
                'time' => date('H:i:s')
            ], '/user', 'test.room');
        }
    }
}

// Initialize the WebSocket server
$server = new Server(
    port: 8000,
    debug: true,
);

$server->registerController(
    controller: new TestController(),
);

$server->run();
