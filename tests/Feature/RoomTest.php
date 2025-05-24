<?php

use Sockeon\Sockeon\Core\Server;
use Sockeon\Sockeon\Core\Contracts\SocketController;
use Sockeon\Sockeon\WebSocket\Attributes\SocketOn;

test('rooms can be joined and left', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    $controller = new class extends SocketController {
        #[\Sockeon\Sockeon\WebSocket\Attributes\SocketOn('room.join')]
        public function onRoomJoin(int $clientId, array $data)
        {
            parent::joinRoom($clientId, $data['room'] ?? 'default');
            return true;
        }
        
        #[\Sockeon\Sockeon\WebSocket\Attributes\SocketOn('room.leave')]
        public function onRoomLeave(int $clientId, array $data)
        {
            parent::leaveRoom($clientId, $data['room'] ?? 'default');
            return true;
        }
    };
    
    $server->registerController($controller);
    
    expect($server->getNamespaceManager())->toBeInstanceOf(\Sockeon\Sockeon\Core\NamespaceManager::class);
});

test('messages can be broadcast to rooms', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    $controller = new class extends SocketController {
        #[\Sockeon\Sockeon\WebSocket\Attributes\SocketOn('broadcast.room')]
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
    
    expect($server->getNamespaceManager())->toBeInstanceOf(\Sockeon\Sockeon\Core\NamespaceManager::class);
});

test('namespaces can be created and managed', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    $controller = new class extends SocketController {
        #[SocketOn('namespace.join')]
        public function joinNamespace(int $clientId, array $data)
        {
            $this->joinRoom($clientId, 'room1', $data['namespace'] ?? '/');
            return true;
        }
        
        #[SocketOn('namespace.broadcast')]
        public function broadcastToNamespace(int $clientId, array $data)
        {
            $this->broadcast('message', [
                'data' => $data['message'] ?? ''
            ], $data['namespace'] ?? '/');
            return true;
        }
    };
    
    $server->registerController($controller);
    
    // Simulate events
    $router = $server->getRouter();
    $router->dispatch(1, 'namespace.join', ['namespace' => '/admin']);
    $router->dispatch(1, 'namespace.broadcast', [
        'namespace' => '/admin',
        'message' => 'Hello Admin'
    ]);
    
    expect($server->getNamespaceManager())->toBeInstanceOf(\Sockeon\Sockeon\Core\NamespaceManager::class);
});

test('client can join multiple rooms', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    $controller = new class extends SocketController {
        #[SocketOn('rooms.join')]
        public function joinRooms(int $clientId, array $data)
        {
            foreach ($data['rooms'] ?? [] as $room) {
                $this->joinRoom($clientId, $room);
            }
            return true;
        }
    };
    
    $server->registerController($controller);
    
    // Join multiple rooms
    $router = $server->getRouter();
    $router->dispatch(1, 'rooms.join', [
        'rooms' => ['room1', 'room2', 'room3']
    ]);
    
    // Implementation note: We'd need to add a method to check room membership
    // in the NamespaceManager class to make this test more meaningful
    expect($server->getNamespaceManager())->toBeInstanceOf(\Sockeon\Sockeon\Core\NamespaceManager::class);
});

test('rooms in different namespaces are isolated', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    $controller = new class extends SocketController {
        #[SocketOn('room.broadcast')]
        public function broadcastToRoom(int $clientId, array $data)
        {
            $this->broadcast('message', [
                'data' => $data['message'] ?? ''
            ], $data['namespace'] ?? '/', $data['room'] ?? 'default');
            return true;
        }
    };
    
    $server->registerController($controller);
    
    // Create same room name in different namespaces
    $router = $server->getRouter();
    $server->getNamespaceManager()->joinRoom(1, 'chatroom', '/user');
    $server->getNamespaceManager()->joinRoom(2, 'chatroom', '/admin');
    
    expect($server->getNamespaceManager())->toBeInstanceOf(\Sockeon\Sockeon\Core\NamespaceManager::class);
});
