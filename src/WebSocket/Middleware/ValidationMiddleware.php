<?php
/**
 * ValidationMiddleware class
 * 
 * Middleware for validating WebSocket event data
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\WebSocket\Middleware;

use Closure;
use Sockeon\Sockeon\Connection\Server;
use Sockeon\Sockeon\Contracts\WebSocket\WebsocketMiddleware;
use Sockeon\Sockeon\Validation\SchemaValidator;
use Sockeon\Sockeon\Exception\Validation\ValidationException;

class ValidationMiddleware implements WebsocketMiddleware
{
    /**
     * Schema validator instance
     * @var SchemaValidator
     */
    protected SchemaValidator $schemaValidator;

    /**
     * Constructor
     * 
     * @param SchemaValidator|null $schemaValidator Optional schema validator instance
     */
    public function __construct(?SchemaValidator $schemaValidator = null)
    {
        $this->schemaValidator = $schemaValidator ?? new SchemaValidator();
    }

    /**
     * Handle the WebSocket event
     * 
     * @param int $clientId The client ID
     * @param string $event The event name
     * @param array<string, mixed> $data The event data
     * @param callable $next The next middleware
     * @param Server $server The server instance
     * @return mixed The result from the next middleware
     */
    public function handle(int $clientId, string $event, array $data, callable $next, Server $server): mixed
    {
        try {
            // Validate event data against schema
            $validatedData = $this->schemaValidator->validateEvent($event, $data);
            
            // Pass validated data to next middleware
            return $next($clientId, $validatedData);
        } catch (ValidationException $e) {
            // Send validation error to client
            $this->sendValidationError($clientId, $event, $e, $server);
            return null;
        }
    }

    /**
     * Register a schema for an event
     * 
     * @param string $event The event name
     * @param array<string, mixed> $schema The validation schema
     * @return void
     */
    public function registerSchema(string $event, array $schema): void
    {
        $this->schemaValidator->registerSchema($event, $schema);
    }

    /**
     * Send validation error to client
     * 
     * @param int $clientId The client ID
     * @param string $event The event name
     * @param ValidationException $e The validation exception
     * @param Server $server The server instance
     * @return void
     */
    protected function sendValidationError(int $clientId, string $event, ValidationException $e, Server $server): void
    {
        $errorData = [
            'event' => 'validation_error',
            'data' => [
                'original_event' => $event,
                'message' => $e->getMessage(),
                'errors' => $e->getErrors()
            ]
        ];

        $server->sendToClient($clientId, json_encode($errorData));
        
        $server->getLogger()->warning("Validation failed for client {$clientId}, event '{$event}': " . $e->getMessage(), [
            'clientId' => $clientId,
            'event' => $event,
            'errors' => $e->getErrors()
        ]);
    }

    /**
     * Get the schema validator instance
     * 
     * @return SchemaValidator The schema validator
     */
    public function getSchemaValidator(): SchemaValidator
    {
        return $this->schemaValidator;
    }
} 