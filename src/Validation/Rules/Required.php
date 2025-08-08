<?php
/**
 * Required rule
 * 
 * Validates that a field is present and not empty
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

class Required extends BaseRule
{
    /**
     * Validate that a value is required
     * 
     * @param mixed $value The value to validate
     * @return bool True if the value is not empty
     */
    public function validate(mixed $value): bool
    {
        return !$this->isEmpty($value);
    }

    /**
     * Get the error message
     * 
     * @param string $fieldName The field name
     * @return string The error message
     */
    public function getMessage(string $fieldName): string
    {
        return "The {$fieldName} field is required.";
    }
} 