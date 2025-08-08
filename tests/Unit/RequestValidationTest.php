<?php

use Sockeon\Sockeon\Http\Request;
use Sockeon\Sockeon\Exception\Validation\ValidationException;

test('basic request validation passes', function () {
    $requestData = [
        'method' => 'POST',
        'path' => '/users',
        'protocol' => 'HTTP/1.1',
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 25
        ])
    ];

    $request = new Request($requestData);

    $rules = [
        'name' => 'required|string|min:3',
        'email' => 'required|email',
        'age' => 'integer|min:18'
    ];

    expect($request->validate($rules))->toBeTrue();
    expect($request->hasValidationErrors())->toBeFalse();
});

test('request validation throws exception on errors', function () {
    $requestData = [
        'method' => 'POST',
        'path' => '/users',
        'protocol' => 'HTTP/1.1',
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'name' => 'Jo', // Too short
            'email' => 'invalid-email', // Invalid email
            'age' => 15 // Too young
        ])
    ];

    $request = new Request($requestData);

    $rules = [
        'name' => 'required|string|min:3',
        'email' => 'required|email',
        'age' => 'integer|min:18'
    ];

    expect(fn() => $request->validate($rules))->toThrow(ValidationException::class);
});

test('validated method returns sanitized data', function () {
    $requestData = [
        'method' => 'POST',
        'path' => '/users',
        'protocol' => 'HTTP/1.1',
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'name' => '  John Doe  ',
            'email' => 'john@example.com',
            'age' => '25'
        ])
    ];

    $request = new Request($requestData);

    $rules = [
        'name' => 'required|string',
        'email' => 'required|email',
        'age' => 'integer'
    ];

    $validatedData = $request->validated($rules);

    expect($validatedData['name'])->toBe('John Doe');
    expect($validatedData['email'])->toBe('john@example.com');
    expect($validatedData['age'])->toBe(25);
});

test('custom error messages work', function () {
    $requestData = [
        'method' => 'POST',
        'path' => '/users',
        'protocol' => 'HTTP/1.1',
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'name' => ''
        ])
    ];

    $request = new Request($requestData);

    $rules = ['name' => 'required'];
    $messages = ['name.required' => 'Please provide your name'];

    try {
        $request->validate($rules, $messages);
    } catch (ValidationException $e) {
        $errors = $request->getValidationErrors();
        expect($errors['name'][0])->toContain('Please provide your name');
    }
});

test('custom field names work', function () {
    $requestData = [
        'method' => 'POST',
        'path' => '/users',
        'protocol' => 'HTTP/1.1',
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'name' => ''
        ])
    ];

    $request = new Request($requestData);

    $rules = ['name' => 'required'];
    $fieldNames = ['name' => 'Full Name'];

    try {
        $request->validate($rules, [], $fieldNames);
    } catch (ValidationException $e) {
        $errors = $request->getValidationErrors();
        expect($errors['name'][0])->toContain('Full Name');
    }
});

test('single field validation works', function () {
    $requestData = [
        'method' => 'POST',
        'path' => '/users',
        'protocol' => 'HTTP/1.1',
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'email' => 'john@example.com'
        ])
    ];

    $request = new Request($requestData);

    expect($request->validateField('email', 'required|email'))->toBeTrue();
});

test('multiple fields validation works', function () {
    $requestData = [
        'method' => 'POST',
        'path' => '/users',
        'protocol' => 'HTTP/1.1',
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ])
    ];

    $request = new Request($requestData);

    $rules = [
        'name' => 'required|string|min:3',
        'email' => 'required|email'
    ];

    expect($request->validateFields($rules))->toBeTrue();
});

test('validation error methods work correctly', function () {
    $requestData = [
        'method' => 'POST',
        'path' => '/users',
        'protocol' => 'HTTP/1.1',
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'name' => 'Jo',
            'email' => 'invalid-email'
        ])
    ];

    $request = new Request($requestData);

    $rules = [
        'name' => 'required|string|min:3',
        'email' => 'required|email'
    ];

    try {
        $request->validate($rules);
    } catch (ValidationException $e) {
        expect($request->hasValidationErrors())->toBeTrue();
        
        $errors = $request->getValidationErrors();
        expect($errors)->toHaveKey('name');
        expect($errors)->toHaveKey('email');
        
        $nameErrors = $request->getFieldValidationErrors('name');
        expect($nameErrors)->not->toBeEmpty();
        
        $firstEmailError = $request->getFirstValidationError('email');
        expect($firstEmailError)->not->toBeNull();
    }
});

test('validation works with query parameters', function () {
    $requestData = [
        'method' => 'GET',
        'path' => '/users',
        'protocol' => 'HTTP/1.1',
        'headers' => [],
        'query' => [
            'page' => '1',
            'limit' => '10'
        ],
        'body' => ''
    ];

    $request = new Request($requestData);

    $rules = [
        'page' => 'integer|min:1',
        'limit' => 'integer|min:1|max:100'
    ];

    expect($request->validate($rules))->toBeTrue();
});

test('validation works with path parameters', function () {
    $requestData = [
        'method' => 'GET',
        'path' => '/users/123',
        'protocol' => 'HTTP/1.1',
        'headers' => [],
        'params' => [
            'id' => '123'
        ],
        'body' => ''
    ];

    $request = new Request($requestData);

    $rules = [
        'id' => 'required|integer|min:1'
    ];

    expect($request->validate($rules))->toBeTrue();
});

test('validation rule setters work', function () {
    $requestData = [
        'method' => 'POST',
        'path' => '/users',
        'protocol' => 'HTTP/1.1',
        'headers' => [],
        'body' => ''
    ];

    $request = new Request($requestData);

    $rules = ['name' => 'required|string'];
    $messages = ['name.required' => 'Please provide your name'];
    $fieldNames = ['name' => 'Full Name'];

    $request->setValidationRules($rules);
    $request->setValidationMessages($messages);
    $request->setValidationFieldNames($fieldNames);

    expect($request->getValidationRules())->toBe($rules);
    expect($request->getValidationMessages())->toBe($messages);
    expect($request->getValidationFieldNames())->toBe($fieldNames);
});

test('validation with complex rules', function () {
    $requestData = [
        'method' => 'POST',
        'path' => '/users',
        'protocol' => 'HTTP/1.1',
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => 'secure123',
            'age' => 25,
            'interests' => ['coding', 'music']
        ])
    ];

    $request = new Request($requestData);

    $rules = [
        'username' => 'required|string|min:3|max:20|alpha_num',
        'email' => 'required|email',
        'password' => 'required|string|min:8',
        'age' => 'integer|min:18|max:120',
        'interests' => 'array|min:1|max:10'
    ];

    expect($request->validate($rules))->toBeTrue();
});

test('validation with enum rules', function () {
    $requestData = [
        'method' => 'POST',
        'path' => '/users',
        'protocol' => 'HTTP/1.1',
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'status' => 'active',
            'role' => 'user'
        ])
    ];

    $request = new Request($requestData);

    $rules = [
        'status' => 'required|in:active,inactive,pending',
        'role' => 'required|in:user,admin,moderator'
    ];

    expect($request->validate($rules))->toBeTrue();
});

test('validation with regex pattern', function () {
    $requestData = [
        'method' => 'POST',
        'path' => '/users',
        'protocol' => 'HTTP/1.1',
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'phone' => '+15551234567',
            'zipcode' => '12345'
        ])
    ];

    $request = new Request($requestData);

    $rules = [
        'phone' => 'required|regex:/^\+?[1-9]\d{1,14}$/',
        'zipcode' => 'required|regex:/^\d{5}(-\d{4})?$/'
    ];

    expect($request->validate($rules))->toBeTrue();
});

test('validation with between rule', function () {
    $requestData = [
        'method' => 'POST',
        'path' => '/users',
        'protocol' => 'HTTP/1.1',
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'score' => 85,
            'rating' => 4.5
        ])
    ];

    $request = new Request($requestData);

    $rules = [
        'score' => 'integer|between:0,100',
        'rating' => 'float|between:1,5'
    ];

    expect($request->validate($rules))->toBeTrue();
});

test('validation with alpha rules', function () {
    $requestData = [
        'method' => 'POST',
        'path' => '/users',
        'protocol' => 'HTTP/1.1',
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe123'
        ])
    ];

    $request = new Request($requestData);

    $rules = [
        'first_name' => 'required|alpha',
        'last_name' => 'required|alpha',
        'username' => 'required|alpha_num'
    ];

    expect($request->validate($rules))->toBeTrue();
});

test('validation with json rule', function () {
    $requestData = [
        'method' => 'POST',
        'path' => '/users',
        'protocol' => 'HTTP/1.1',
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'metadata' => '{"key": "value", "nested": {"data": "test"}}',
            'settings' => '{"theme": "dark", "notifications": true}'
        ])
    ];

    $request = new Request($requestData);

    $rules = [
        'metadata' => 'required|json',
        'settings' => 'required|json'
    ];

    expect($request->validate($rules))->toBeTrue();
});

test('validation fails with invalid json', function () {
    $requestData = [
        'method' => 'POST',
        'path' => '/users',
        'protocol' => 'HTTP/1.1',
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'metadata' => '{"key": "value", "invalid": json}'
        ])
    ];

    $request = new Request($requestData);

    $rules = [
        'metadata' => 'required|json'
    ];

    expect(fn() => $request->validate($rules))->toThrow(ValidationException::class);
}); 