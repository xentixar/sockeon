<?php
/**
 * ArrayRule
 * 
 * Validates that a value is an array
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

class ArrayRule extends BaseRule
{
    /**
     * Validate that a value is an array
     * 
     * @param mixed $value The value to validate
     * @return bool True if the value is an array
     */
    public function validate(mixed $value): bool
    {
        if ($this->isEmpty($value)) {
            return true; // Allow empty values, use required rule if needed
        }
        return is_array($value);
    }

    /**
     * Sanitize a value to array
     * 
     * @param mixed $value The value to sanitize
     * @return array The sanitized array value
     */
    public function sanitize(mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        return [$value];
    }

    /**
     * Get the error message
     * 
     * @param string $fieldName The field name
     * @return string The error message
     */
    public function getMessage(string $fieldName): string
    {
        return "The {$fieldName} field must be an array.";
    }
} 