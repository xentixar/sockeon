<?php

use Sockeon\Sockeon\Config\ServerConfig;
use Sockeon\Sockeon\Connection\Server;

test('system room controller is registered by default', function () {
    $config = new ServerConfig([
        'host' => '127.0.0.1',
        'port' => 6001,
    ]);

    $server = new Server($config);
    $router = $server->getRouter();

    // Verify that join_room and leave_room events are registered
    $routes = $router->getWebSocketRoutes();
    expect($routes)->toHaveKey('join_room');
    expect($routes)->toHaveKey('leave_room');
});

test('system room controller can be disabled', function () {
    $config = new ServerConfig([
        'host' => '127.0.0.1',
        'port' => 6002,
        'register_system_controllers' => false,
    ]);

    $server = new Server($config);
    $router = $server->getRouter();

    // Verify that system controller events are NOT registered
    $routes = $router->getWebSocketRoutes();
    expect($routes)->not->toHaveKey('join_room');
    expect($routes)->not->toHaveKey('leave_room');
});

test('system room controller handles join_room event', function () {
    $config = new ServerConfig([
        'host' => '127.0.0.1',
        'port' => 6003,
    ]);

    $server = new Server($config);
    $router = $server->getRouter();

    // Dispatch join_room event
    $router->dispatch('test-client-1', 'join_room', [
        'room' => 'test-room',
        'namespace' => '/',
    ]);

    // Verify client is in the room
    $clients = $server->getNamespaceManager()->getClientsInRoom('test-room', '/');
    expect($clients)->toHaveKey('test-client-1');
});

test('system room controller handles leave_room event', function () {
    $config = new ServerConfig([
        'host' => '127.0.0.1',
        'port' => 6004,
    ]);

    $server = new Server($config);
    $router = $server->getRouter();

    // First join the room
    $router->dispatch('test-client-2', 'join_room', [
        'room' => 'test-room-2',
        'namespace' => '/',
    ]);

    // Verify client is in the room
    $clientsBefore = $server->getNamespaceManager()->getClientsInRoom('test-room-2', '/');
    expect($clientsBefore)->toHaveKey('test-client-2');

    // Now leave the room
    $router->dispatch('test-client-2', 'leave_room', [
        'room' => 'test-room-2',
        'namespace' => '/',
    ]);

    // Verify client is no longer in the room
    $clientsAfter = $server->getNamespaceManager()->getClientsInRoom('test-room-2', '/');
    expect($clientsAfter)->not->toHaveKey('test-client-2');
});

test('system room controller works with custom namespaces', function () {
    $config = new ServerConfig([
        'host' => '127.0.0.1',
        'port' => 6007,
    ]);

    $server = new Server($config);
    $router = $server->getRouter();

    // Join room in custom namespace
    $router->dispatch('test-client-5', 'join_room', [
        'room' => 'admin-room',
        'namespace' => '/admin',
    ]);

    // Verify client is in the correct namespace and room
    $clients = $server->getNamespaceManager()->getClientsInRoom('admin-room', '/admin');
    expect($clients)->toHaveKey('test-client-5');

    // Verify client is NOT in the default namespace
    $defaultClients = $server->getNamespaceManager()->getClientsInRoom('admin-room', '/');
    expect($defaultClients)->not->toHaveKey('test-client-5');
});

