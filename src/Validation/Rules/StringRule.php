<?php
/**
 * StringRule
 * 
 * Validates that a value is a string
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

class StringRule extends BaseRule
{
    /**
     * Validate that a value is a string
     * 
     * @param mixed $value The value to validate
     * @return bool True if the value is a string
     */
    public function validate(mixed $value): bool
    {
        if ($this->isEmpty($value)) {
            return true; // Allow empty values, use required rule if needed
        }
        return is_string($value);
    }

    /**
     * Sanitize a value to string
     * 
     * @param mixed $value The value to sanitize
     * @return string The sanitized string value
     */
    public function sanitize(mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return '';
        }
        /** @var string $value */
        return trim((string) $value);
    }

    /**
     * Get the error message
     * 
     * @param string $fieldName The field name
     * @return string The error message
     */
    public function getMessage(string $fieldName): string
    {
        return "The {$fieldName} field must be a string.";
    }
} 