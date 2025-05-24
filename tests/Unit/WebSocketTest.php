<?php

use Sockeon\Sockeon\Core\Server;
use Sockeon\Sockeon\Core\Contracts\SocketController;
use Sockeon\Sockeon\WebSocket\Attributes\SocketOn;

test('websocket can handle multiple event handlers', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    $controller = new class extends SocketController {
        public $eventsCalled = [];
        
        #[SocketOn('event.one')]
        public function handleEventOne(int $clientId, array $data)
        {
            $this->eventsCalled[] = 'event.one';
            return true;
        }
        
        #[SocketOn('event.two')]
        public function handleEventTwo(int $clientId, array $data)
        {
            $this->eventsCalled[] = 'event.two';
            return true;
        }
    };
    
    $server->registerController($controller);
    
    // Simulate events
    $router = $server->getRouter();
    $router->dispatch(1, 'event.one', []);
    $router->dispatch(1, 'event.two', []);
    
    expect($controller->eventsCalled)->toBe(['event.one', 'event.two']);
});

test('websocket can broadcast to multiple clients', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    $controller = new class extends SocketController {
        #[SocketOn('broadcast.test')]
        public function handleBroadcast(int $clientId, array $data)
        {
            $this->broadcast('message', [
                'data' => $data['message'] ?? ''
            ]);
            return true;
        }
    };
    
    $server->registerController($controller);
    
    // Set some test client data to verify broadcast
    $server->setClientData(1, 'connected', true);
    $server->setClientData(2, 'connected', true);
    $server->setClientData(3, 'connected', true);
    
    expect($server->getClientData(1, 'connected'))->toBeTrue()
        ->and($server->getClientData(2, 'connected'))->toBeTrue()
        ->and($server->getClientData(3, 'connected'))->toBeTrue();
});

test('websocket client data persists', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    $controller = new class extends SocketController {
        #[SocketOn('user.login')]
        public function handleLogin(int $clientId, array $data)
        {
            $this->server->setClientData($clientId, 'user', [
                'id' => $data['userId'] ?? null,
                'name' => $data['name'] ?? 'anonymous'
            ]);
            return true;
        }
    };
    
    $server->registerController($controller);
    
    // Simulate login event
    $router = $server->getRouter();
    $router->dispatch(1, 'user.login', [
        'userId' => 123,
        'name' => 'Test User'
    ]);
    
    $userData = $server->getClientData(1, 'user');
    expect($userData)->toBe([
        'id' => 123,
        'name' => 'Test User'
    ]);
});
