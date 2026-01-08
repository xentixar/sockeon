<?php

/**
 * BooleanRule
 *
 * Validates that a value is a boolean
 *
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

class BooleanRule extends BaseRule
{
    /**
     * Validate that a value is a boolean
     *
     * @param mixed $value The value to validate
     * @return bool True if the value is a boolean
     */
    public function validate(mixed $value): bool
    {
        if ($this->isEmpty($value)) {
            return true; // Allow empty values, use required rule if needed
        }
        return is_bool($value) || in_array($value, ['0', '1', 'true', 'false', 0, 1], true);
    }

    /**
     * Sanitize a value to boolean
     *
     * @param mixed $value The value to sanitize
     * @return bool The sanitized boolean value
     */
    public function sanitize(mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }
        return (bool) $value;
    }

    /**
     * Get the error message
     *
     * @param string $fieldName The field name
     * @return string The error message
     */
    public function getMessage(string $fieldName): string
    {
        return "The {$fieldName} field must be a boolean.";
    }
}
