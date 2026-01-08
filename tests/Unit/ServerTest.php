<?php

use Sockeon\Sockeon\Connection\Server;
use Sockeon\Sockeon\Contracts\Http\HttpMiddleware;
use Sockeon\Sockeon\Contracts\WebSocket\WebsocketMiddleware;
use Sockeon\Sockeon\Controllers\SocketController;
use Sockeon\Sockeon\Core\Middleware;
use Sockeon\Sockeon\Core\Router;
use Sockeon\Sockeon\Http\Handler;
use Sockeon\Sockeon\Http\Request;
use Sockeon\Sockeon\WebSocket\Attributes\SocketOn;

test('server can be instantiated with default configuration', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line

    expect($server)->toBeInstanceOf(Server::class)
        ->and($server->getRouter())->toBeInstanceOf(Router::class)
        ->and($server->getHttpHandler())->toBeInstanceOf(Handler::class);
});

test('server can register controllers', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line

    // Mock controller
    $controller = new class extends SocketController {
        /**
         * @param string $clientId
         * @param array<string, mixed> $data
         * @return bool
         */
        #[SocketOn('test.event')]
        public function testEvent(string $clientId, array $data): bool
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
     * @param string $clientId
     * @param string $event
     * @param array<string, mixed> $data
     * @param callable $next
     * @return mixed
     */
    $middleware = function (string $clientId, string $event, array $data, callable $next) {
        return $next();
    };

    $httpMiddleware = function (Request $request, callable $next) {
        return $next();
    };

    $server->addWebSocketMiddleware(TestWebSocketMiddleware::class);
    $server->addHttpMiddleware(TestHttpMiddleware::class);

    expect($server->getMiddleware())->toBeInstanceOf(Middleware::class);
});

class TestHttpMiddleware implements HttpMiddleware
{
    public function handle(Request $request, callable $next, Server $server): mixed
    {
        return $next($request);
    }
}

class TestWebSocketMiddleware implements WebSocketMiddleware
{
    public function handle(string $clientId, string $event, array $data, callable $next, Server $server): mixed
    {
        return $next();
    }
}
