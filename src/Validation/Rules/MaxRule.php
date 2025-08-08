<?php
/**
 * MaxRule
 * 
 * Validates that a value meets maximum requirements
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

class MaxRule extends BaseRule
{
    /**
     * Validate that a value meets maximum requirements
     * 
     * @param mixed $value The value to validate
     * @return bool True if the value meets maximum requirements
     */
    public function validate(mixed $value): bool
    {
        if ($this->isEmpty($value)) {
            return true; // Allow empty values, use required rule if needed
        }

        $max = $this->getFirstParameter();
        if ($max === null) {
            return true;
        }

        if (is_numeric($value)) {
            return (float) $value <= (float) $max;
        }

        if (is_string($value)) {
            return mb_strlen($value) <= (int) $max;
        }

        if (is_array($value)) {
            return count($value) <= (int) $max;
        }

        return false;
    }

    /**
     * Get the error message
     * 
     * @param string $fieldName The field name
     * @return string The error message
     */
    public function getMessage(string $fieldName): string
    {
        $max = $this->getFirstParameter();
        return "The {$fieldName} field must not exceed {$max}.";
    }
} 