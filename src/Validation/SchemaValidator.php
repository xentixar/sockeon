<?php
/**
 * SchemaValidator class
 * 
 * Provides schema validation for event payloads
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation;

use Sockeon\Sockeon\Exception\Validation\ValidationException;

class SchemaValidator
{
    /**
     * Registered schemas
     * @var array<string, array<string, mixed>>
     */
    protected array $schemas = [];

    /**
     * Validator instance
     * @var Validator
     */
    protected Validator $validator;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->validator = new Validator();
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
        $this->schemas[$event] = $schema;
    }

    /**
     * Validate event data against its schema
     * 
     * @param string $event The event name
     * @param array<string, mixed> $data The event data
     * @return array<string, mixed> The validated and sanitized data
     * @throws ValidationException
     */
    public function validateEvent(string $event, array $data): array
    {
        if (!isset($this->schemas[$event])) {
            return $data; // No schema defined, return data as-is
        }

        $schema = $this->schemas[$event];
        $rules = $this->buildRulesFromSchema($schema);
        
        try {
            $this->validator->validate($data, $rules);
            return $this->validator->getSanitized();
        } catch (ValidationException $e) {
            throw new ValidationException(
                "Event '{$event}' validation failed: " . $e->getMessage(),
                $e->getErrors()
            );
        }
    }

    /**
     * Build validation rules from schema
     * 
     * @param array<string, mixed> $schema The schema definition
     * @return array<string, string|array<int, string>> The validation rules
     */
    protected function buildRulesFromSchema(array $schema): array
    {
        $rules = [];

        foreach ($schema as $field => $fieldSchema) {
            if (is_string($fieldSchema)) {
                $rules[$field] = $fieldSchema;
            } elseif (is_array($fieldSchema)) {
                $rules[$field] = $this->buildFieldRules($fieldSchema);
            }
        }

        return $rules;
    }

    /**
     * Build rules for a single field
     * 
     * @param array<string, mixed> $fieldSchema The field schema
     * @return array<int, string> The field rules
     */
    protected function buildFieldRules(array $fieldSchema): array
    {
        $rules = [];

        // Type validation
        if (isset($fieldSchema['type'])) {
            $rules[] = $fieldSchema['type'];
        }

        // Required validation
        if (isset($fieldSchema['required']) && $fieldSchema['required']) {
            $rules[] = 'required';
        }

        // Min/Max validation
        if (isset($fieldSchema['min'])) {
            $rules[] = "min:{$fieldSchema['min']}";
        }

        if (isset($fieldSchema['max'])) {
            $rules[] = "max:{$fieldSchema['max']}";
        }

        // Pattern validation
        if (isset($fieldSchema['pattern'])) {
            $rules[] = "regex:{$fieldSchema['pattern']}";
        }

        // Enum validation
        if (isset($fieldSchema['enum'])) {
            $enumValues = implode(',', $fieldSchema['enum']);
            $rules[] = "in:{$enumValues}";
        }

        // Custom validation
        if (isset($fieldSchema['rules'])) {
            $rules = array_merge($rules, $fieldSchema['rules']);
        }

        return $rules;
    }

    /**
     * Check if a schema exists for an event
     * 
     * @param string $event The event name
     * @return bool True if schema exists
     */
    public function hasSchema(string $event): bool
    {
        return isset($this->schemas[$event]);
    }

    /**
     * Get all registered schemas
     * 
     * @return array<string, array<string, mixed>> All registered schemas
     */
    public function getSchemas(): array
    {
        return $this->schemas;
    }

    /**
     * Remove a schema
     * 
     * @param string $event The event name
     * @return void
     */
    public function removeSchema(string $event): void
    {
        unset($this->schemas[$event]);
    }

    /**
     * Clear all schemas
     * 
     * @return void
     */
    public function clearSchemas(): void
    {
        $this->schemas = [];
    }
} 