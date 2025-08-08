<?php
/**
 * IntegerRule
 * 
 * Validates that a value is an integer
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

class IntegerRule extends BaseRule
{
    /**
     * Validate that a value is an integer
     * 
     * @param mixed $value The value to validate
     * @return bool True if the value is an integer
     */
    public function validate(mixed $value): bool
    {
        if ($this->isEmpty($value)) {
            return true; // Allow empty values, use required rule if needed
        }
        return is_int($value) || (is_string($value) && ctype_digit($value));
    }

    /**
     * Sanitize a value to integer
     * 
     * @param mixed $value The value to sanitize
     * @return int The sanitized integer value
     */
    public function sanitize(mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return 0;
        }
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Get the error message
     * 
     * @param string $fieldName The field name
     * @return string The error message
     */
    public function getMessage(string $fieldName): string
    {
        return "The {$fieldName} field must be an integer.";
    }
} 