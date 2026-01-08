<?php

use Sockeon\Sockeon\Http\Request;
use Sockeon\Sockeon\Http\Response;

test('http request can be created with method and path', function () {
    $request = new Request([
        'method' => 'GET',
        'path' => '/api/test',
        'headers' => [],
        'query' => [],
        'body' => null,
    ]);

    expect($request->getMethod())->toBe('GET')
        ->and($request->getPath())->toBe('/api/test');
});

test('http response can be created with json data', function () {
    $data = ['status' => 'success', 'message' => 'Test message'];
    $response = Response::json($data);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getHeader('Content-Type'))->toBe('application/json');
});

test('http request handles query parameters', function () {
    $request = new Request([
        'method' => 'GET',
        'path' => '/api/test',
        'headers' => [],
        'query' => ['page' => '1', 'limit' => '10'],
        'body' => null,
    ]);

    expect($request->getQuery('page'))->toBe('1')
        ->and($request->getQuery('limit'))->toBe('10')
        ->and($request->getQuery('nonexistent', 'default'))->toBe('default');
});

test('http response can handle various status codes', function () {
    $notFound = Response::notFound(['error' => 'Resource not found']);
    expect($notFound->getStatusCode())->toBe(404);

    $serverError = Response::serverError('Internal error');
    expect($serverError->getStatusCode())->toBe(500);

    $unauthorized = Response::unauthorized(['error' => 'Please login']);
    expect($unauthorized->getStatusCode())->toBe(401);

    $forbidden = Response::forbidden(['error' => 'No access']);
    expect($forbidden->getStatusCode())->toBe(403);
});

test('http request handles path parameters', function () {
    $request = new Request([
        'method' => 'GET',
        'path' => '/users/123',
        'headers' => [],
        'params' => ['id' => '123'],
        'query' => [],
        'body' => null,
    ]);

    expect($request->getParam('id'))->toBe('123');
});

test('http request handles json body', function () {
    $request = new Request([
        'method' => 'POST',
        'path' => '/api/users',
        'headers' => ['Content-Type' => 'application/json'],
        'query' => [],
        'body' => json_encode(['name' => 'John Doe']),
    ]);

    expect($request->isJson())->toBeTrue()
        ->and($request->getBody())->toBe(['name' => 'John Doe']);
});

test('http response can set and get headers', function () {
    $response = new Response('Test content');
    $response->setHeader('X-Custom', 'test-value')
             ->setContentType('text/plain');

    expect($response->getHeader('X-Custom'))->toBe('test-value')
        ->and($response->getHeader('Content-Type'))->toBe('text/plain');
});
