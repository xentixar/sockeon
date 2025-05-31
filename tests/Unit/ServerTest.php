<?php

use Sockeon\Sockeon\Core\Contracts\SocketController;
use Sockeon\Sockeon\Core\Middleware;
use Sockeon\Sockeon\Core\Server;
use Sockeon\Sockeon\Core\Router;
use Sockeon\Sockeon\Http\HttpHandler;
use Sockeon\Sockeon\Http\Request;
use Sockeon\Sockeon\WebSocket\Attributes\SocketOn;
use Sockeon\Sockeon\WebSocket\WebSocketHandler;

test('server can be instantiated with default configuration', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line
    
    expect($server)->toBeInstanceOf(Server::class)
        ->and($server->getRouter())->toBeInstanceOf(Router::class)
        ->and($server->getHttpHandler())->toBeInstanceOf(HttpHandler::class);
});

test('server can register controllers', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line
    
    // Mock controller
    $controller = new class extends SocketController {
        /**
         * @param int $clientId
         * @param array<string, mixed> $data
         * @return bool
         */
        #[SocketOn('test.event')]
        public function testEvent(int $clientId, array $data): bool
        {
            return true;
        }
    };
    
    $server->registerController($controller);
    
    expect($server->getRouter())->toBeInstanceOf(Router::class);
});

test('server adds middleware correctly', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line

    /**
     * @param int $clientId
     * @param string $event
     * @param array<string, mixed> $data
     * @param callable $next
     * @return mixed
     */
    $middleware = function (int $clientId, string $event,array $data, callable $next) {
        return $next();
    };
    
    $httpMiddleware = function (Request $request, callable $next) {
        return $next();
    };
    
    $server->addWebSocketMiddleware($middleware);
    $server->addHttpMiddleware($httpMiddleware);
    
    expect($server->getMiddleware())->toBeInstanceOf(Middleware::class);
});
