<?php
/**
 * Example Socklet application
 * 
 * This file demonstrates how to use the Socklet library for WebSocket and HTTP handling
 * 
 * @package     Xentixar\Socklet
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

require __DIR__ . '/../vendor/autoload.php';

use Xentixar\Socklet\Core\Server;
use Xentixar\Socklet\Core\Contracts\SocketController;
use Xentixar\Socklet\WebSocket\Attributes\SocketOn;
use Xentixar\Socklet\Http\Attributes\HttpRoute;

class AppController extends SocketController
{
    /**
     * Handle message.send WebSocket event
     * 
     * Processes incoming messages and broadcasts them to appropriate recipients
     * 
     * @param int   $clientId   The ID of the client sending the message
     * @param array $data       Message data containing 'message' and optional 'room'
     * @return void
     */
    #[SocketOn('message.send')]
    public function onMessageSend(int $clientId, array $data)
    {
        if (!empty($data['room'])) {
            $this->joinRoom($clientId, $data['room']);
        }

        if (!empty($data['room'])) {
            $this->broadcast('message.receive', [
                'from' => $clientId,
                'message' => $data['message'],
                'room' => $data['room']
            ], '/', $data['room']);
        } else {
            $this->broadcast('message.receive', [
                'from' => $this->server->getClientData($clientId, 'user')['name'],
                'message' => $data['message']
            ]);
        }
    }
    
    /**
     * Handle room.join WebSocket event
     * 
     * Adds a client to a specific room and notifies them when joined
     * 
     * @param int   $clientId   The ID of the client joining the room
     * @param array $data       Data containing the 'room' to join
     * @return void
     */
    #[SocketOn('room.join')]
    public function onRoomJoin(int $clientId, array $data)
    {
        if (!empty($data['room'])) {
            $this->joinRoom($clientId, $data['room']);
            $this->emit($clientId, 'room.joined', [
                'room' => $data['room']
            ]);
        }
    }
    
    /**
     * Handle GET request to /api/status endpoint
     * 
     * Returns the current` server status and timestamp
     * 
     * @param array $request    HTTP request data
     * @return array            Status information
     */
    #[HttpRoute('GET', '/api/status')]
    public function getStatus($request)
    {
        return [
            'status' => 'online',
            'time' => date('Y-m-d H:i:s')
        ];
    }
}

// Initialize server instance on all interfaces, port 8000, with debugging enabled
$server = new Server("0.0.0.0", 8000, true);

/**
 * Add WebSocket middleware to log events and set up client data
 * 
 * @param int       $clientId   Client identifier
 * @param string    $event      Event name
 * @param mixed     $data       Event data
 * @param callable  $next       Next middleware
 * @return mixed                Result of next middleware
 */
$server->addWebSocketMiddleware(function ($clientId, $event, $data, $next) use ($server) {
    echo "WebSocket Event: $event from client $clientId\n";
    
    $server->setClientData($clientId, 'user', [
        'name' => 'User ' . $clientId,
        'id' => $clientId,
    ]);

    return $next();
});

/**
 * Add HTTP middleware to log incoming requests
 * 
 * @param array     $request    HTTP request data
 * @param callable  $next       Next middleware
 * @return mixed                Result of next middleware
 */
$server->addHttpMiddleware(function ($request, $next) {
    echo "HTTP Request: {$request['method']} {$request['path']}\n";
    return $next();
});

// Register the application controller
$server->registerController(new AppController());

// Start the server
echo "Server running at http://0.0.0.0:8000\n";
$server->run();
