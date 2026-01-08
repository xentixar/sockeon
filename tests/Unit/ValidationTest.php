<?php

use Sockeon\Sockeon\Validation\Validator;
use Sockeon\Sockeon\Validation\Sanitizer;
use Sockeon\Sockeon\Exception\Validation\ValidationException;

beforeEach(function () {
    $this->validator = new Validator();
});

test('basic validation', function () {
    $data = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 25,
    ];

    $rules = [
        'name' => 'required|string|min:3',
        'email' => 'required|email',
        'age' => 'integer|min:18',
    ];

    $this->validator->validate($data, $rules);
    expect($this->validator->hasErrors())->toBeFalse();
});

test('validation with errors', function () {
    $data = [
        'name' => 'Jo', // Too short
        'email' => 'invalid-email', // Invalid email
        'age' => 15, // Too young
    ];

    $rules = [
        'name' => 'required|string|min:3',
        'email' => 'required|email',
        'age' => 'integer|min:18',
    ];

    expect(fn() => $this->validator->validate($data, $rules))->toThrow(ValidationException::class);
});

test('string sanitization', function () {
    $input = "  <script>alert('xss')</script>John Doe  ";
    $result = Sanitizer::string($input, true, true);
    expect($result)->toBe("alert('xss')John Doe");
});

test('email sanitization', function () {
    $input = "  JOHN@EXAMPLE.COM  ";
    $result = Sanitizer::email($input);
    expect($result)->toBe("john@example.com");
});

test('integer sanitization', function () {
    $input = "25";
    $result = Sanitizer::integer($input, 0);
    expect($result)->toBe(25);
});

test('boolean sanitization', function () {
    expect(Sanitizer::boolean("true"))->toBeTrue();
    expect(Sanitizer::boolean("1"))->toBeTrue();
    expect(Sanitizer::boolean("yes"))->toBeTrue();
    expect(Sanitizer::boolean("false"))->toBeFalse();
    expect(Sanitizer::boolean("0"))->toBeFalse();
});

test('array sanitization', function () {
    $input = '["item1", "item2"]';
    $result = Sanitizer::array($input);
    expect($result)->toBe(["item1", "item2"]);
});

test('url sanitization', function () {
    $input = "example.com";
    $result = Sanitizer::url($input);
    expect($result)->toBe("http://example.com");
});

test('date sanitization', function () {
    $input = "2023-12-25";
    $result = Sanitizer::date($input, 'Y-m-d');
    expect($result)->toBe("2023-12-25");
});

test('required rule', function () {
    $data = ['name' => ''];
    $rules = ['name' => 'required'];

    expect(fn() => $this->validator->validate($data, $rules))->toThrow(ValidationException::class);
});

test('min rule', function () {
    $data = ['name' => 'Jo'];
    $rules = ['name' => 'string|min:3'];

    expect(fn() => $this->validator->validate($data, $rules))->toThrow(ValidationException::class);
});

test('max rule', function () {
    $data = ['name' => 'Very Long Name That Exceeds Maximum'];
    $rules = ['name' => 'string|max:10'];

    expect(fn() => $this->validator->validate($data, $rules))->toThrow(ValidationException::class);
});

test('email rule', function () {
    $data = ['email' => 'invalid-email'];
    $rules = ['email' => 'email'];

    expect(fn() => $this->validator->validate($data, $rules))->toThrow(ValidationException::class);
});

test('in rule', function () {
    $data = ['status' => 'invalid'];
    $rules = ['status' => 'in:active,inactive'];

    expect(fn() => $this->validator->validate($data, $rules))->toThrow(ValidationException::class);
});

test('between rule', function () {
    $data = ['age' => 15];
    $rules = ['age' => 'integer|between:18,65'];

    expect(fn() => $this->validator->validate($data, $rules))->toThrow(ValidationException::class);
});

test('custom error messages', function () {
    $data = ['name' => ''];
    $rules = ['name' => 'required'];
    $messages = ['name.required' => 'Please provide your name'];

    try {
        $this->validator->validate($data, $rules, $messages);
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
        expect($errors['name'][0])->toContain('Please provide your name');
    }
});

test('sanitized data', function () {
    $data = [
        'name' => '  John Doe  ',
        'email' => 'john@example.com',
        'age' => '25',
    ];

    $rules = [
        'name' => 'required|string',
        'email' => 'required|email',
        'age' => 'integer',
    ];

    $this->validator->validate($data, $rules);
    $sanitized = $this->validator->getSanitized();

    expect($sanitized['name'])->toBe('John Doe');
    expect($sanitized['email'])->toBe('john@example.com');
    expect($sanitized['age'])->toBe(25);
});

test('alpha rule', function () {
    $data = ['name' => 'John123'];
    $rules = ['name' => 'alpha'];

    expect(fn() => $this->validator->validate($data, $rules))->toThrow(ValidationException::class);
});

test('alpha_num rule', function () {
    $data = ['username' => 'john_doe'];
    $rules = ['username' => 'alpha_num'];

    expect(fn() => $this->validator->validate($data, $rules))->toThrow(ValidationException::class);
});

test('numeric rule', function () {
    $data = ['code' => 'ABC123'];
    $rules = ['code' => 'numeric'];

    expect(fn() => $this->validator->validate($data, $rules))->toThrow(ValidationException::class);
});

test('regex rule', function () {
    $data = ['phone' => 'invalid-phone'];
    $rules = ['phone' => 'regex:/^\+?[1-9]\d{1,14}$/'];

    expect(fn() => $this->validator->validate($data, $rules))->toThrow(ValidationException::class);
});

test('json rule', function () {
    $data = ['config' => 'invalid json'];
    $rules = ['config' => 'json'];

    expect(fn() => $this->validator->validate($data, $rules))->toThrow(ValidationException::class);
});

test('float rule', function () {
    $data = ['price' => 'not a number'];
    $rules = ['price' => 'float'];

    expect(fn() => $this->validator->validate($data, $rules))->toThrow(ValidationException::class);
});

test('boolean rule', function () {
    $data = ['active' => 'not boolean'];
    $rules = ['active' => 'boolean'];

    expect(fn() => $this->validator->validate($data, $rules))->toThrow(ValidationException::class);
});

test('array rule', function () {
    $data = ['tags' => 'not an array'];
    $rules = ['tags' => 'array'];

    expect(fn() => $this->validator->validate($data, $rules))->toThrow(ValidationException::class);
});

test('url rule', function () {
    $data = ['website' => 'not a url'];
    $rules = ['website' => 'url'];

    expect(fn() => $this->validator->validate($data, $rules))->toThrow(ValidationException::class);
});

test('not_in rule', function () {
    $data = ['role' => 'admin'];
    $rules = ['role' => 'not_in:admin,super_admin'];

    expect(fn() => $this->validator->validate($data, $rules))->toThrow(ValidationException::class);
});
