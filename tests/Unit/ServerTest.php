<?php

use Xentixar\Socklet\Core\Server;
use Xentixar\Socklet\Core\Router;
use Xentixar\Socklet\Http\HttpHandler;
use Xentixar\Socklet\WebSocket\WebSocketHandler;

test('server can be instantiated with default configuration', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    expect($server)->toBeInstanceOf(Server::class)
        ->and($server->getRouter())->toBeInstanceOf(Router::class)
        ->and($server->getHttpHandler())->toBeInstanceOf(HttpHandler::class);
});

test('server can register controllers', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    // Mock controller
    $controller = new class extends \Xentixar\Socklet\Core\Contracts\SocketController {
        #[\Xentixar\Socklet\WebSocket\Attributes\SocketOn('test.event')]
        public function testEvent($clientId, $data) {
            return true;
        }
    };
    
    $server->registerController($controller);
    
    expect($server->getRouter())->toBeInstanceOf(Router::class);
});

test('server adds middleware correctly', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    
    $middleware = function ($clientId, $event, $data, $next) {
        return $next();
    };
    
    $httpMiddleware = function ($request, $next) {
        return $next();
    };
    
    $server->addWebSocketMiddleware($middleware);
    $server->addHttpMiddleware($httpMiddleware);
    
    expect($server->getMiddleware())->toBeInstanceOf(\Xentixar\Socklet\Core\Middleware::class);
});
