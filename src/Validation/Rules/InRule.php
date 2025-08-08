<?php
/**
 * InRule
 * 
 * Validates that a value is in a list of allowed values
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

class InRule extends BaseRule
{
    /**
     * Validate that a value is in the allowed list
     * 
     * @param mixed $value The value to validate
     * @return bool True if the value is in the allowed list
     */
    public function validate(mixed $value): bool
    {
        if ($this->isEmpty($value)) {
            return true; // Allow empty values, use required rule if needed
        }

        return in_array($value, $this->parameters, true);
    }

    /**
     * Get the error message
     * 
     * @param string $fieldName The field name
     * @return string The error message
     */
    public function getMessage(string $fieldName): string
    {
        $allowed = implode(', ', $this->parameters);
        return "The {$fieldName} field must be one of: {$allowed}.";
    }
} 