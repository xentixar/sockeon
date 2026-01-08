<?php

use Sockeon\Sockeon\Connection\Server;
use Sockeon\Sockeon\Controllers\SocketController;
use Sockeon\Sockeon\Http\Attributes\HttpRoute;
use Sockeon\Sockeon\Http\Request;

test('router can handle http routes with parameters', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line

    $controller = new class extends SocketController {
        /**
         * @param Request $request
         * @return array<string, mixed>
         */
        #[HttpRoute('GET', '/users/{id}')]
        public function getUser(Request $request): array
        {
            return [
                'id' => $request->getParam('id'),
                'found' => true,
            ];
        }
    };

    $server->registerController($controller);

    $request = new Request([
        'method' => 'GET',
        'path' => '/users/123',
        'headers' => [],
        'query' => [],
    ]);

    $result = $server->getRouter()->dispatchHttp($request);
    expect($result)->toBe([
        'id' => '123',
        'found' => true,
    ]);
});

test('router matches exact routes before parameterized routes', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line

    $controller = new class extends SocketController {
        /**
         * @param Request $request
         * @return array<string, mixed>
         */
        #[HttpRoute('GET', '/users/all')]
        public function getAllUsers(Request $request): array
        {
            return ['route' => 'all'];
        }

        /**
         * @param Request $request
         * @return array<string, mixed>
         */
        #[HttpRoute('GET', '/users/{id}')]
        public function getUser(Request $request): array
        {
            return ['route' => 'single'];
        }
    };

    $server->registerController($controller);

    $request1 = new Request([
        'method' => 'GET',
        'path' => '/users/all',
        'headers' => [],
        'query' => [],
    ]);

    $result1 = $server->getRouter()->dispatchHttp($request1);
    expect($result1)->toBe(['route' => 'all']);

    $request2 = new Request([
        'method' => 'GET',
        'path' => '/users/123',
        'headers' => [],
        'query' => [],
    ]);

    $result2 = $server->getRouter()->dispatchHttp($request2);
    expect($result2)->toBe(['route' => 'single']);
});

test('router handles multiple http methods for same path', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line

    $controller = new class extends SocketController {
        /**
         * @param Request $request
         * @return string[]
         */
        #[HttpRoute('GET', '/api/resource')]
        public function getResource(Request $request): array
        {
            return ['method' => 'GET'];
        }

        /**
         * @param Request $request
         * @return string[]
         */
        #[HttpRoute('POST', '/api/resource')]
        public function createResource(Request $request): array
        {
            return ['method' => 'POST'];
        }

        /**
         * @param Request $request
         * @return string[]
         */
        #[HttpRoute('PUT', '/api/resource')]
        public function updateResource(Request $request): array
        {
            return ['method' => 'PUT'];
        }

        /**
         * @param Request $request
         * @return string[]
         */
        #[HttpRoute('DELETE', '/api/resource')]
        public function deleteResource(Request $request): array
        {
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
            'query' => [],
        ]);

        $result = $server->getRouter()->dispatchHttp($request);
        expect($result)->toBe(['method' => $method]);
    }
});
