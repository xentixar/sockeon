<?php

use Sockeon\Sockeon\Core\Server;
use Sockeon\Sockeon\Core\Contracts\SocketController;
use Sockeon\Sockeon\Http\Attributes\HttpRoute;
use Sockeon\Sockeon\Http\Request;
use Sockeon\Sockeon\WebSocket\Attributes\SocketOn;

test('websocket middleware is executed in order', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line

    $middlewareOrder = [];

    $server->addWebSocketMiddleware(function (int $clientId, string $event, array $data, callable $next) use (&$middlewareOrder) {
        $middlewareOrder[] = 1;
        $result = $next();
        $middlewareOrder[] = 4;
        return $result;
    });

    $server->addWebSocketMiddleware(function (int $clientId, string $event, array $data, callable $next) use (&$middlewareOrder) {
        $middlewareOrder[] = 2;
        $result = $next();
        $middlewareOrder[] = 3;
        return $result;
    });

    $controller = new class extends SocketController {
        /**
         * @param int $clientId
         * @param array<string, mixed> $data
         * @return bool
         */
        #[SocketOn('test.event')]
        public function handle(int $clientId, array $data): bool
        {
            return true;
        }
    };

    $server->registerController($controller);

    $server->getMiddleware()->runWebSocketStack(1, 'test.event', [], function () {
        return true;
    });

    expect($middlewareOrder)->toBe([1, 2, 3, 4]);
});

test('http middleware is executed in order', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line

    $middlewareOrder = [];

    $server->addHttpMiddleware(function (Request $request, callable $next) use (&$middlewareOrder) {
        $middlewareOrder[] = 1;
        $result = $next();
        $middlewareOrder[] = 4;
        return $result;
    });

    $server->addHttpMiddleware(function (Request $request, callable $next) use (&$middlewareOrder) {
        $middlewareOrder[] = 2;
        $result = $next();
        $middlewareOrder[] = 3;
        return $result;
    });

    $request = new Request([
        'method' => 'GET',
        'path' => '/test',
        'headers' => [],
        'query' => [],
        'body' => null
    ]);

    $server->getMiddleware()->runHttpStack($request, function () {
        return true;
    });

    expect($middlewareOrder)->toBe([1, 2, 3, 4]);
});

test('middleware can modify request data', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line

    $server->addHttpMiddleware(function (Request $request, callable $next) {
        $request->setData('timestamp', time());
        $request->setData('modified', true);
        return $next();
    });

    $controller = new class extends SocketController {
        /**
         * @param Request $request
         * @return array<string, mixed>
         */
        #[HttpRoute('GET', '/test')]
        public function handleTest(Request $request): array
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
    expect($result['modified'])->toBeTrue(); //@phpstan-ignore-line
});

test('middleware can handle authentication', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line

    $server->addWebSocketMiddleware(function (int $clientId, string $event, array $data, callable $next) use ($server) {
        if (!isset($data['token']) || $data['token'] !== 'valid-token') {
            return false;
        }
        $server->setClientData($clientId, 'authenticated', true);
        return $next();
    });

    $controller = new class extends SocketController {
        /**
         * @param int $clientId
         * @param array<string, mixed> $data
         * @return bool
         */
        #[SocketOn('protected.event')]
        public function handleProtected(int $clientId, array $data): bool
        {
            return $this->server->getClientData($clientId, 'authenticated') === true;
        }
    };

    $server->registerController($controller);

    $server->getMiddleware()->runWebSocketStack(1, 'protected.event',
        ['token' => 'invalid'],
        function () {
            return true;
        }
    );
    expect($server->getClientData(1, 'authenticated'))->toBeNull();

    $server->getMiddleware()->runWebSocketStack(1, 'protected.event',
        ['token' => 'valid-token'],
        function () {
            return true;
        }
    );
    expect($server->getClientData(1, 'authenticated'))->toBeTrue();
});

test('middleware can handle errors', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line

    $server->addHttpMiddleware(function (Request $request, callable $next) {
        try {
            return $next();
        } catch (Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    });

    $controller = new class extends SocketController {
        #[HttpRoute('GET', '/error')]
        public function handleError(Request $request): void
        {
            throw new Exception('Test error');
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
