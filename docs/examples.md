# Examples

This document provides comprehensive examples of using the Socklet library for various use cases.

## Basic Chat Application

### Server Code

```php
<?php

use Xentixar\Socklet\Core\Server;
use Xentixar\Socklet\Core\Contracts\SocketController;
use Xentixar\Socklet\WebSocket\Attributes\SocketOn;
use Xentixar\Socklet\Http\Attributes\HttpRoute;

class ChatController extends SocketController
{
    #[SocketOn('message.send')]
    public function onMessageSend(int $clientId, array $data)
    {
        $this->broadcast('message.receive', [
            'from' => $this->server->getClientData($clientId, 'user')['name'],
            'message' => $data['message']
        ]);
    }

    #[SocketOn('room.join')]
    public function onRoomJoin(int $clientId, array $data)
    {
        $room = $data['room'] ?? null;
        if ($room) {
            $this->joinRoom($clientId, $room);
            $this->emit($clientId, 'room.joined', [
                'room' => $room
            ]);
        }
    }

    #[HttpRoute('GET', '/api/status')]
    public function getStatus($request)
    {
        return [
            'status' => 'online',
            'time' => date('Y-m-d H:i:s')
        ];
    }
}

$server = new Server("0.0.0.0", 8000, true);

// Add middleware for user data
$server->addWebSocketMiddleware(function ($clientId, $event, $data, $next) use ($server) {
    $server->setClientData($clientId, 'user', [
        'name' => 'User ' . $clientId,
        'id' => $clientId,
    ]);
    return $next();
});

$server->registerController(new ChatController());
$server->run();
```

### Client Code

```html
<!DOCTYPE html>
<html>
<head>
    <title>Socklet Chat</title>
    <style>
        .message-list {
            height: 400px;
            overflow-y: auto;
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="message-list" id="messages"></div>
    <input type="text" id="messageInput" placeholder="Type your message...">
    <button onclick="sendMessage()">Send</button>
    
    <div>
        <input type="text" id="roomInput" placeholder="Room name">
        <button onclick="joinRoom()">Join Room</button>
    </div>

    <script>
        const socket = new WebSocket('ws://localhost:8000');
        const messages = document.getElementById('messages');
        const messageInput = document.getElementById('messageInput');
        const roomInput = document.getElementById('roomInput');
        let currentRoom = null;

        socket.onopen = () => {
            addMessage('System', 'Connected to server');
        };

        socket.onmessage = (event) => {
            const data = JSON.parse(event.data);
            
            if (data.event === 'message.receive') {
                addMessage(data.data.from, data.data.message);
            } else if (data.event === 'room.joined') {
                currentRoom = data.data.room;
                addMessage('System', `Joined room: ${currentRoom}`);
            }
        };

        function sendMessage() {
            const message = messageInput.value;
            if (message) {
                const data = {
                    message: message
                };
                
                if (currentRoom) {
                    data.room = currentRoom;
                }
                
                socket.send(JSON.stringify({
                    event: 'message.send',
                    data: data
                }));
                
                messageInput.value = '';
            }
        }

        function joinRoom() {
            const room = roomInput.value;
            if (room) {
                socket.send(JSON.stringify({
                    event: 'room.join',
                    data: { room: room }
                }));
                roomInput.value = '';
            }
        }

        function addMessage(from, text) {
            const div = document.createElement('div');
            div.textContent = `${from}: ${text}`;
            messages.appendChild(div);
            messages.scrollTop = messages.scrollHeight;
        }
    </script>
</body>
</html>
```

## Game Server Example

```php
class GameController extends SocketController
{
    protected array $games = [];

    #[SocketOn('game.create')]
    public function onGameCreate(int $clientId, array $data)
    {
        $gameId = uniqid();
        $this->games[$gameId] = [
            'id' => $gameId,
            'players' => [$clientId],
            'state' => 'waiting'
        ];
        
        $this->joinRoom($clientId, "game-{$gameId}");
        $this->emit($clientId, 'game.created', [
            'gameId' => $gameId
        ]);
    }

    #[SocketOn('game.join')]
    public function onGameJoin(int $clientId, array $data)
    {
        $gameId = $data['gameId'] ?? null;
        if ($gameId && isset($this->games[$gameId])) {
            $this->games[$gameId]['players'][] = $clientId;
            $this->joinRoom($clientId, "game-{$gameId}");
            
            $this->broadcast('game.playerJoined', [
                'gameId' => $gameId,
                'playerId' => $clientId
            ], '/', "game-{$gameId}");
        }
    }

    #[SocketOn('game.move')]
    public function onGameMove(int $clientId, array $data)
    {
        $gameId = $data['gameId'] ?? null;
        if ($gameId && isset($this->games[$gameId])) {
            $this->broadcast('game.moveUpdate', [
                'gameId' => $gameId,
                'playerId' => $clientId,
                'move' => $data['move']
            ], '/', "game-{$gameId}");
        }
    }
}
```

## Real-time Dashboard Example

```php
class DashboardController extends SocketController
{
    #[SocketOn('dashboard.subscribe')]
    public function onSubscribe(int $clientId, array $data)
    {
        $metrics = $data['metrics'] ?? [];
        foreach ($metrics as $metric) {
            $this->joinRoom($clientId, "metric-{$metric}");
        }
        
        $this->emit($clientId, 'dashboard.subscribed', [
            'metrics' => $metrics
        ]);
    }

    #[HttpRoute('POST', '/api/metrics/update')]
    public function updateMetrics($request)
    {
        $metric = $request['body']['metric'] ?? null;
        $value = $request['body']['value'] ?? null;
        
        if ($metric && $value !== null) {
            $this->broadcast('metric.update', [
                'metric' => $metric,
                'value' => $value,
                'timestamp' => time()
            ], '/', "metric-{$metric}");
            
            return ['status' => 'success'];
        }
        
        return ['status' => 'error', 'message' => 'Invalid metric data'];
    }
}
```

For more details about the concepts used in these examples, refer to:
- [Core Concepts](./core-concepts.md)
- [API Reference](./api-reference.md)
