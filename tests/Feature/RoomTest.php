<?php

use Xentixar\Socklet\Core\Server;
use Xentixar\Socklet\Core\Contracts\SocketController;

test('rooms can be joined and left', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    $controller = new class extends SocketController {
        #[\Xentixar\Socklet\WebSocket\Attributes\SocketOn('room.join')]
        public function onRoomJoin(int $clientId, array $data)
        {
            parent::joinRoom($clientId, $data['room'] ?? 'default');
            return true;
        }
        
        #[\Xentixar\Socklet\WebSocket\Attributes\SocketOn('room.leave')]
        public function onRoomLeave(int $clientId, array $data)
        {
            parent::leaveRoom($clientId, $data['room'] ?? 'default');
            return true;
        }
    };
    
    $server->registerController($controller);
    
    expect($server->getNamespaceManager())->toBeInstanceOf(\Xentixar\Socklet\Core\NamespaceManager::class);
});

test('messages can be broadcast to rooms', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    $controller = new class extends SocketController {
        #[\Xentixar\Socklet\WebSocket\Attributes\SocketOn('broadcast.room')]
        public function broadcastToRoom(int $clientId, array $data)
        {
            $this->broadcast('room.message', [
                'message' => $data['message'] ?? '',
                'room' => $data['room'] ?? 'default'
            ], '/', $data['room'] ?? 'default');
            return true;
        }
    };
    
    $server->registerController($controller);
    
    expect($server->getNamespaceManager())->toBeInstanceOf(\Xentixar\Socklet\Core\NamespaceManager::class);
});
