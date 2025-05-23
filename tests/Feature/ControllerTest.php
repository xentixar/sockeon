<?php

use Xentixar\Socklet\Core\Server;
use Xentixar\Socklet\Core\Contracts\SocketController;
use Xentixar\Socklet\WebSocket\Attributes\SocketOn;
use Xentixar\Socklet\Http\Attributes\HttpRoute;
use Xentixar\Socklet\Http\Request;
use Xentixar\Socklet\Http\Response;

class TestController extends SocketController 
{
    #[SocketOn('message.send')]
    public function handleMessage(int $clientId, array $data)
    {
        return $this->broadcast('message.receive', [
            'message' => $data['message'] ?? '',
            'from' => $clientId
        ]);
    }

    #[HttpRoute('GET', '/api/status')]
    public function getStatus(Request $request)
    {
        return Response::json([
            'status' => 'online',
            'timestamp' => time()
        ]);
    }
}

test('controller can handle websocket events', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    $controller = new TestController();
    
    $server->registerController($controller);
    
    expect($server->getRouter())->toBeInstanceOf(\Xentixar\Socklet\Core\Router::class);
});

test('controller routes are registered correctly', function () {
    $port = get_test_port();
    $server = new Server('127.0.0.1', $port);
    $controller = new TestController();
    
    $server->registerController($controller);
    
    $router = $server->getRouter();
    
    // Create a test HTTP request
    $request = new Request([
        'method' => 'GET',
        'path' => '/api/status',
        'headers' => [],
        'query' => [],
        'body' => null
    ]);
    
    $response = $router->dispatchHttp($request);
    
    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getBody())->toHaveKey('status')
        ->and($response->getBody())->toHaveKey('timestamp');
});
