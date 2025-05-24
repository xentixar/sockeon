<?php

use Sockeon\Sockeon\Core\Server;
use Sockeon\Sockeon\Core\Contracts\SocketController;
use Sockeon\Sockeon\Http\Request;

test('websocket middleware is executed in order', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    $middlewareOrder = [];
    
    $server->addWebSocketMiddleware(function ($clientId, $event, $data, $next) use (&$middlewareOrder) {
        $middlewareOrder[] = 1;
        $result = $next();
        $middlewareOrder[] = 4;
        return $result;
    });
    
    $server->addWebSocketMiddleware(function ($clientId, $event, $data, $next) use (&$middlewareOrder) {
        $middlewareOrder[] = 2;
        $result = $next();
        $middlewareOrder[] = 3;
        return $result;
    });
    
    $controller = new class extends SocketController {
        #[\Sockeon\Sockeon\WebSocket\Attributes\SocketOn('test.event')]
        public function handle($clientId, $data) {
            return true;
        }
    };
    
    $server->registerController($controller);
    
    // Simulate middleware execution
    $server->getMiddleware()->runWebSocketStack(1, 'test.event', [], function() {
        return true;
    });
    
    expect($middlewareOrder)->toBe([1, 2, 3, 4]);
});

test('http middleware is executed in order', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    $middlewareOrder = [];
    
    $server->addHttpMiddleware(function ($request, $next) use (&$middlewareOrder) {
        $middlewareOrder[] = 1;
        $result = $next();
        $middlewareOrder[] = 4;
        return $result;
    });
    
    $server->addHttpMiddleware(function ($request, $next) use (&$middlewareOrder) {
        $middlewareOrder[] = 2;
        $result = $next();
        $middlewareOrder[] = 3;
        return $result;
    });
    
    // Simulate middleware execution
    $request = new Request([
        'method' => 'GET',
        'path' => '/test',
        'headers' => [],
        'query' => [],
        'body' => null
    ]);
    
    $server->getMiddleware()->runHttpStack($request, function() {
        return true;
    });
    
    expect($middlewareOrder)->toBe([1, 2, 3, 4]);
});

test('middleware can modify request data', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    // Add middleware that modifies request
    $server->addHttpMiddleware(function ($request, $next) {
        $request->setData('timestamp', time());
        $request->setData('modified', true);
        return $next();
    });
    
    $controller = new class extends SocketController {
        #[\Sockeon\Sockeon\Http\Attributes\HttpRoute('GET', '/test')]
        public function handleTest($request)
        {
            return [
                'timestamp' => $request->getData('timestamp'),
                'modified' => $request->getData('modified')
            ];
        }
    };
    
    $server->registerController($controller);
    
    $request = new Request([
        'method' => 'GET',
        'path' => '/test',
        'headers' => [],
        'query' => []
    ]);
    
    $result = $server->getRouter()->dispatchHttp($request);
    expect($result['modified'])->toBeTrue();
});

test('middleware can handle authentication', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    // Add auth middleware
    $server->addWebSocketMiddleware(function ($clientId, $event, $data, $next) use ($server) {
        if (!isset($data['token']) || $data['token'] !== 'valid-token') {
            return false;
        }
        $server->setClientData($clientId, 'authenticated', true);
        return $next();
    });
    
    $controller = new class extends SocketController {
        #[\Sockeon\Sockeon\WebSocket\Attributes\SocketOn('protected.event')]
        public function handleProtected($clientId, $data) {
            return $this->server->getClientData($clientId, 'authenticated') === true;
        }
    };
    
    $server->registerController($controller);
    
    // Test with invalid auth
    $server->getMiddleware()->runWebSocketStack(1, 'protected.event', 
        ['token' => 'invalid'], 
        function() { return true; }
    );
    expect($server->getClientData(1, 'authenticated'))->toBeNull();
    
    // Test with valid auth
    $server->getMiddleware()->runWebSocketStack(1, 'protected.event', 
        ['token' => 'valid-token'], 
        function() { return true; }
    );
    expect($server->getClientData(1, 'authenticated'))->toBeTrue();
});

test('middleware can handle errors', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    // Add error handling middleware
    $server->addHttpMiddleware(function ($request, $next) {
        try {
            return $next();
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    });
    
    $controller = new class extends SocketController {
        #[\Sockeon\Sockeon\Http\Attributes\HttpRoute('GET', '/error')]
        public function handleError($request) {
            throw new \Exception('Test error');
        }
    };
    
    $server->registerController($controller);
    
    $request = new Request([
        'method' => 'GET',
        'path' => '/error',
        'headers' => [],
        'query' => []
    ]);
    
    $result = $server->getRouter()->dispatchHttp($request);
    expect($result)->toBe([
        'error' => true,
        'message' => 'Test error'
    ]);
});
