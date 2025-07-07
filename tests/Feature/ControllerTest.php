<?php

use Sockeon\Sockeon\Core\Router;
use Sockeon\Sockeon\Core\Server;
use Sockeon\Sockeon\Core\Contracts\SocketController;
use Sockeon\Sockeon\WebSocket\Attributes\SocketOn;
use Sockeon\Sockeon\WebSocket\Attributes\OnConnect;
use Sockeon\Sockeon\WebSocket\Attributes\OnDisconnect;
use Sockeon\Sockeon\Http\Attributes\HttpRoute;
use Sockeon\Sockeon\Http\Request;
use Sockeon\Sockeon\Http\Response;

class TestController extends SocketController 
{
    /**
     * @param int $clientId
     * @param array<string, mixed> $data
     * @return void
     */
    #[SocketOn('message.send')]
    public function handleMessage(int $clientId, array $data): void
    {
        $this->broadcast('message.receive', [
            'message' => $data['message'] ?? '',
            'from' => $clientId
        ]);
    }

    /**
     * Special event: called when a client connects
     * @param int $clientId
     * @return void
     */
    #[OnConnect]
    public function onClientConnect(int $clientId): void
    {
        $this->emit($clientId, 'welcome', [
            'message' => 'Welcome to the server!',
            'clientId' => $clientId
        ]);
        
        $this->broadcast('user.joined', [
            'clientId' => $clientId,
            'message' => "User $clientId joined the server"
        ]);
    }

    /**
     * Special event: called when a client disconnects
     * @param int $clientId
     * @return void
     */
    #[OnDisconnect]
    public function onClientDisconnect(int $clientId): void
    {
        $this->broadcast('user.left', [
            'clientId' => $clientId,
            'message' => "User $clientId left the server"
        ]);
    }

    #[HttpRoute('GET', '/api/status')]
    public function getStatus(Request $request): Response
    {
        return Response::json([
            'status' => 'online',
            'timestamp' => time()
        ]);
    }
}

test('controller can handle websocket events', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line

    $controller = new TestController();
    
    $server->registerController($controller);
    
    expect($server->getRouter())->toBeInstanceOf(Router::class);
});

test('controller routes are registered correctly', function () {
    /** @var Server $server */
    $server = $this->server; //@phpstan-ignore-line

    $controller = new TestController();
    
    $server->registerController($controller);
    
    $router = $server->getRouter();
    
    $request = new Request([
        'method' => 'GET',
        'path' => '/api/status',
        'headers' => [],
        'query' => [],
        'body' => null
    ]);
    
    $response = $router->dispatchHttp($request);
    
    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getBody())->toHaveKey('status') //@phpstan-ignore-line
        ->and($response->getBody())->toHaveKey('timestamp'); //@phpstan-ignore-line
});
