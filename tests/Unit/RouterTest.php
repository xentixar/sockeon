<?php

use Xentixar\Socklet\Core\Server;
use Xentixar\Socklet\Core\Router;
use Xentixar\Socklet\Core\Contracts\SocketController;
use Xentixar\Socklet\Http\Request;
use Xentixar\Socklet\Http\Response;

test('router can handle http routes with parameters', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    $controller = new class extends SocketController {
        #[\Xentixar\Socklet\Http\Attributes\HttpRoute('GET', '/users/{id}')]
        public function getUser($request) {
            return [
                'id' => $request->getParam('id'),
                'found' => true
            ];
        }
    };
    
    $server->registerController($controller);
    
    $request = new Request([
        'method' => 'GET',
        'path' => '/users/123',
        'headers' => [],
        'query' => []
    ]);
    
    $result = $server->getRouter()->dispatchHttp($request);
    expect($result)->toBe([
        'id' => '123',
        'found' => true
    ]);
});

test('router matches exact routes before parameterized routes', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    $controller = new class extends SocketController {
        #[\Xentixar\Socklet\Http\Attributes\HttpRoute('GET', '/users/all')]
        public function getAllUsers($request) {
            return ['route' => 'all'];
        }
        
        #[\Xentixar\Socklet\Http\Attributes\HttpRoute('GET', '/users/{id}')]
        public function getUser($request) {
            return ['route' => 'single'];
        }
    };
    
    $server->registerController($controller);
    
    // Test exact route
    $request1 = new Request([
        'method' => 'GET',
        'path' => '/users/all',
        'headers' => [],
        'query' => []
    ]);
    
    $result1 = $server->getRouter()->dispatchHttp($request1);
    expect($result1)->toBe(['route' => 'all']);
    
    // Test parameterized route
    $request2 = new Request([
        'method' => 'GET',
        'path' => '/users/123',
        'headers' => [],
        'query' => []
    ]);
    
    $result2 = $server->getRouter()->dispatchHttp($request2);
    expect($result2)->toBe(['route' => 'single']);
});

test('router handles multiple http methods for same path', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    $controller = new class extends SocketController {
        #[\Xentixar\Socklet\Http\Attributes\HttpRoute('GET', '/api/resource')]
        public function getResource($request) {
            return ['method' => 'GET'];
        }
        
        #[\Xentixar\Socklet\Http\Attributes\HttpRoute('POST', '/api/resource')]
        public function createResource($request) {
            return ['method' => 'POST'];
        }
        
        #[\Xentixar\Socklet\Http\Attributes\HttpRoute('PUT', '/api/resource')]
        public function updateResource($request) {
            return ['method' => 'PUT'];
        }
        
        #[\Xentixar\Socklet\Http\Attributes\HttpRoute('DELETE', '/api/resource')]
        public function deleteResource($request) {
            return ['method' => 'DELETE'];
        }
    };
    
    $server->registerController($controller);
    
    $methods = ['GET', 'POST', 'PUT', 'DELETE'];
    
    foreach ($methods as $method) {
        $request = new Request([
            'method' => $method,
            'path' => '/api/resource',
            'headers' => [],
            'query' => []
        ]);
        
        $result = $server->getRouter()->dispatchHttp($request);
        expect($result)->toBe(['method' => $method]);
    }
});
