<?php

use Sockeon\Sockeon\Connection\Server;
use Sockeon\Sockeon\Controllers\SocketController;
use Sockeon\Sockeon\Contracts\Http\HttpMiddleware;
use Sockeon\Sockeon\Contracts\WebSocket\WebsocketMiddleware;
use Sockeon\Sockeon\Http\Attributes\HttpRoute;
use Sockeon\Sockeon\Http\Request;
use Sockeon\Sockeon\WebSocket\Attributes\SocketOn;

class TestGlobalHttpMiddleware implements HttpMiddleware
{
    public static array $calls = [];

    public function handle(Request $request, callable $next, Server $server): mixed
    {
        self::$calls[] = 'global_http';
        return $next($request);
    }
}

class TestGlobalWebSocketMiddleware implements WebsocketMiddleware
{
    public static array $calls = [];

    public function handle(string $clientId, string $event, array $data, callable $next, Server $server): mixed
    {
        self::$calls[] = 'global_ws';
        return $next();
    }
}

class TestRouteSpecificHttpMiddleware implements HttpMiddleware
{
    public static array $calls = [];

    public function handle(Request $request, callable $next, Server $server): mixed
    {
        self::$calls[] = 'route_specific_http';
        return $next($request);
    }
}

class TestRouteSpecificWebSocketMiddleware implements WebsocketMiddleware
{
    public static array $calls = [];

    public function handle(string $clientId, string $event, array $data, callable $next, Server $server): mixed
    {
        self::$calls[] = 'route_specific_ws';
        return $next();
    }
}

beforeEach(function () {
    TestGlobalHttpMiddleware::$calls = [];
    TestGlobalWebSocketMiddleware::$calls = [];
    TestRouteSpecificHttpMiddleware::$calls = [];
    TestRouteSpecificWebSocketMiddleware::$calls = [];
});

test('websocket event can exclude global middleware', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line

    $server->addWebSocketMiddleware(TestGlobalWebSocketMiddleware::class);

    $controller = new class extends SocketController {
        /**
         * @param string $clientId
         * @param array<string, mixed> $data
         * @return bool
         */
        #[SocketOn('test.normal')]
        public function normalEvent(string $clientId, array $data): bool
        {
            return true;
        }

        /**
         * @param string $clientId
         * @param array<string, mixed> $data
         * @return bool
         */
        #[SocketOn('test.excluded', middlewares: [TestRouteSpecificWebSocketMiddleware::class], excludeGlobalMiddlewares: [TestGlobalWebSocketMiddleware::class])]
        public function excludedEvent(string $clientId, array $data): bool
        {
            return true;
        }
    };

    $server->registerController($controller);

    $server->getRouter()->dispatch(1, 'test.normal', []);
    expect(TestGlobalWebSocketMiddleware::$calls)->toContain('global_ws');

    TestGlobalWebSocketMiddleware::$calls = [];
    TestRouteSpecificWebSocketMiddleware::$calls = [];

    $server->getRouter()->dispatch(1, 'test.excluded', []);
    expect(TestGlobalWebSocketMiddleware::$calls)->toBeEmpty();
    expect(TestRouteSpecificWebSocketMiddleware::$calls)->toContain('route_specific_ws');
});

test('http route can exclude global middleware', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line

    $server->addHttpMiddleware(TestGlobalHttpMiddleware::class);

    $controller = new class extends SocketController {
        /**
         * @param Request $request
         * @return array<string, mixed>
         */
        #[HttpRoute('GET', '/normal')]
        public function normalRoute(Request $request): array
        {
            return ['status' => 'normal'];
        }

        /**
         * @param Request $request
         * @return array<string, mixed>
         */
        #[HttpRoute('GET', '/excluded', middlewares: [TestRouteSpecificHttpMiddleware::class], excludeGlobalMiddlewares: [TestGlobalHttpMiddleware::class])]
        public function excludedRoute(Request $request): array
        {
            return ['status' => 'excluded'];
        }
    };

    $server->registerController($controller);

    $request1 = new Request([
        'method' => 'GET',
        'path' => '/normal',
        'headers' => [],
        'query' => [],
    ]);
    $server->getRouter()->dispatchHttp($request1);
    expect(TestGlobalHttpMiddleware::$calls)->toContain('global_http');

    TestGlobalHttpMiddleware::$calls = [];
    TestRouteSpecificHttpMiddleware::$calls = [];

    $request2 = new Request([
        'method' => 'GET',
        'path' => '/excluded',
        'headers' => [],
        'query' => [],
    ]);
    $server->getRouter()->dispatchHttp($request2);
    expect(TestGlobalHttpMiddleware::$calls)->toBeEmpty();
    expect(TestRouteSpecificHttpMiddleware::$calls)->toContain('route_specific_http');
});

test('multiple middlewares can be excluded', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line

    $server->addWebSocketMiddleware(TestGlobalWebSocketMiddleware::class);
    $server->addWebSocketMiddleware(TestRouteSpecificWebSocketMiddleware::class);

    $controller = new class extends SocketController {
        /**
         * @param string $clientId
         * @param array<string, mixed> $data
         * @return bool
         */
        #[SocketOn('test.exclude-multiple', excludeGlobalMiddlewares: [TestGlobalWebSocketMiddleware::class, TestRouteSpecificWebSocketMiddleware::class])]
        public function excludeMultipleEvent(string $clientId, array $data): bool
        {
            return true;
        }
    };

    $server->registerController($controller);

    $server->getRouter()->dispatch(1, 'test.exclude-multiple', []);
    expect(TestGlobalWebSocketMiddleware::$calls)->toBeEmpty();
    expect(TestRouteSpecificWebSocketMiddleware::$calls)->toBeEmpty();
});

test('partial exclusion works correctly', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line

    $anotherGlobalMiddleware = new class implements WebsocketMiddleware {
        public static array $calls = [];

        public function handle(string $clientId, string $event, array $data, callable $next, Server $server): mixed
        {
            self::$calls[] = 'another_global_ws';
            return $next();
        }
    };

    $server->addWebSocketMiddleware(TestGlobalWebSocketMiddleware::class);
    $server->addWebSocketMiddleware($anotherGlobalMiddleware::class);

    $controller = new class extends SocketController {
        /**
         * @param string $clientId
         * @param array<string, mixed> $data
         * @return bool
         */
        #[SocketOn('test.partial-exclude', excludeGlobalMiddlewares: [TestGlobalWebSocketMiddleware::class])]
        public function partialExcludeEvent(string $clientId, array $data): bool
        {
            return true;
        }
    };

    $server->registerController($controller);

    $server->getRouter()->dispatch(1, 'test.partial-exclude', []);
    expect(TestGlobalWebSocketMiddleware::$calls)->toBeEmpty();
    expect($anotherGlobalMiddleware::$calls)->toContain('another_global_ws');
});
