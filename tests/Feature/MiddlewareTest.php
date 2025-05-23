<?php

use Xentixar\Socklet\Core\Server;
use Xentixar\Socklet\Core\Contracts\SocketController;
use Xentixar\Socklet\Http\Request;

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
        #[\Xentixar\Socklet\WebSocket\Attributes\SocketOn('test.event')]
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
