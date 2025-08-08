<?php
/**
 * AlphaNumericRule
 * 
 * Validates that a value contains only alphanumeric characters
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

class AlphaNumericRule extends BaseRule
{
    /**
     * Validate that a value contains only alphanumeric characters
     * 
     * @param mixed $value The value to validate
     * @return bool True if the value contains only alphanumeric characters
     */
    public function validate(mixed $value): bool
    {
        if ($this->isEmpty($value)) {
            return true; // Allow empty values, use required rule if needed
        }

        if (!is_string($value)) {
            return false;
        }

        return ctype_alnum($value);
    }

    /**
     * Sanitize an alphanumeric value
     * 
     * @param mixed $value The value to sanitize
     * @return string The sanitized alphanumeric value
     */
    public function sanitize(mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return '';
        }
        $value = (string) $value;
        return preg_replace('/[^a-zA-Z0-9]/', '', $value);
    }

    /**
     * Get the error message
     * 
     * @param string $fieldName The field name
     * @return string The error message
     */
    public function getMessage(string $fieldName): string
    {
        return "The {$fieldName} field must contain only alphanumeric characters.";
    }
} 