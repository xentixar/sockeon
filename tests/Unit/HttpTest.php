<?php

use Xentixar\Socklet\Http\Request;
use Xentixar\Socklet\Http\Response;

test('http request can be created with method and path', function () {
    $request = new Request([
        'method' => 'GET',
        'path' => '/api/test',
        'headers' => [],
        'query' => [],
        'body' => null
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
        'body' => null
    ]);
    
    expect($request->getQuery('page'))->toBe('1')
        ->and($request->getQuery('limit'))->toBe('10')
        ->and($request->getQuery('nonexistent', 'default'))->toBe('default');
});
