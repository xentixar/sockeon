<?php

/**
 * JsonRule
 *
 * Validates that a value is valid JSON
 *
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

class JsonRule extends BaseRule
{
    /**
     * Validate that a value is valid JSON
     *
     * @param mixed $value The value to validate
     * @return bool True if the value is valid JSON
     */
    public function validate(mixed $value): bool
    {
        if ($this->isEmpty($value)) {
            return true; // Allow empty values, use required rule if needed
        }

        if (!is_string($value)) {
            return false;
        }

        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Sanitize a JSON value
     *
     * @param mixed $value The value to sanitize
     * @return mixed The sanitized JSON value
     */
    public function sanitize(mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    /**
     * Get the error message
     *
     * @param string $fieldName The field name
     * @return string The error message
     */
    public function getMessage(string $fieldName): string
    {
        return "The {$fieldName} field must be valid JSON.";
    }
}
