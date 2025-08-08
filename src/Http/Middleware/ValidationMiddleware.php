<?php
/**
 * ValidationMiddleware class
 * 
 * Middleware for validating HTTP request data
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http\Middleware;

use Closure;
use Sockeon\Sockeon\Connection\Server;
use Sockeon\Sockeon\Contracts\Http\HttpMiddleware;
use Sockeon\Sockeon\Http\Request;
use Sockeon\Sockeon\Http\Response;
use Sockeon\Sockeon\Validation\Validator;
use Sockeon\Sockeon\Exception\Validation\ValidationException;

class ValidationMiddleware implements HttpMiddleware
{
    /**
     * Validation rules
     * @var array<string, string|array<int, string>>
     */
    protected array $rules;

    /**
     * Custom error messages
     * @var array<string, string>
     */
    protected array $messages;

    /**
     * Custom field names
     * @var array<string, string>
     */
    protected array $fieldNames;

    /**
     * Constructor
     * 
     * @param array<string, string|array<int, string>> $rules Validation rules
     * @param array<string, string> $messages Custom error messages
     * @param array<string, string> $fieldNames Custom field names
     */
    public function __construct(array $rules = [], array $messages = [], array $fieldNames = [])
    {
        $this->rules = $rules;
        $this->messages = $messages;
        $this->fieldNames = $fieldNames;
    }

    /**
     * Handle the HTTP request
     * 
     * @param Request $request The HTTP request
     * @param callable $next The next middleware
     * @param Server $server The server instance
     * @return mixed The HTTP response
     */
    public function handle(Request $request, callable $next, Server $server): mixed
    {
        if (empty($this->rules)) {
            return $next($request);
        }

        try {
            $validator = new Validator();
            $data = $this->extractData($request);
            
            $validator->validate($data, $this->rules, $this->messages, $this->fieldNames);
            
            // Add sanitized data to request attributes
            foreach ($validator->getSanitized() as $key => $value) {
                $request->setAttribute("validated_{$key}", $value);
            }
            
            return $next($request);
        } catch (ValidationException $e) {
            return $this->createErrorResponse($e);
        }
    }

    /**
     * Extract data from request for validation
     * 
     * @param Request $request The HTTP request
     * @return array<string, mixed> The data to validate
     */
    protected function extractData(Request $request): array
    {
        $data = [];

        // Merge query parameters, body data, and path parameters
        $data = array_merge(
            $request->getQueryParams(),
            $request->all(),
            $request->getPathParams()
        );

        return $data;
    }

    /**
     * Create error response for validation failures
     * 
     * @param ValidationException $e The validation exception
     * @return Response The error response
     */
    protected function createErrorResponse(ValidationException $e): Response
    {
        $errors = $e->getErrors();
        $errorMessages = [];

        foreach ($errors as $field => $fieldErrors) {
            $errorMessages[$field] = $fieldErrors[0] ?? 'Validation failed';
        }

        $response = new Response();
        $response->setStatusCode(422);
        $response->setHeader('Content-Type', 'application/json');
        $response->setBody(json_encode([
            'error' => 'Validation failed',
            'message' => $e->getMessage(),
            'errors' => $errorMessages
        ]));

        return $response;
    }
} 