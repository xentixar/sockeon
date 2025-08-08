<?php
/**
 * NumericRule
 * 
 * Validates that a value is numeric
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

class NumericRule extends BaseRule
{
    /**
     * Validate that a value is numeric
     * 
     * @param mixed $value The value to validate
     * @return bool True if the value is numeric
     */
    public function validate(mixed $value): bool
    {
        if ($this->isEmpty($value)) {
            return true; // Allow empty values, use required rule if needed
        }

        return is_numeric($value);
    }

    /**
     * Sanitize a numeric value
     * 
     * @param mixed $value The value to sanitize
     * @return float The sanitized numeric value
     */
    public function sanitize(mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return 0.0;
        }
        return (float) $value;
    }

    /**
     * Get the error message
     * 
     * @param string $fieldName The field name
     * @return string The error message
     */
    public function getMessage(string $fieldName): string
    {
        return "The {$fieldName} field must be numeric.";
    }
} 