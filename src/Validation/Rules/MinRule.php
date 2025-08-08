<?php
/**
 * MinRule
 * 
 * Validates that a value meets minimum requirements
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

class MinRule extends BaseRule
{
    /**
     * Validate that a value meets minimum requirements
     * 
     * @param mixed $value The value to validate
     * @return bool True if the value meets minimum requirements
     */
    public function validate(mixed $value): bool
    {
        if ($this->isEmpty($value)) {
            return true; // Allow empty values, use required rule if needed
        }

        $min = $this->getFirstParameter();
        if ($min === null) {
            return true;
        }

        if (is_numeric($value)) {
            return (float) $value >= (float) $min;
        }

        if (is_string($value)) {
            return mb_strlen($value) >= (int) $min;
        }

        if (is_array($value)) {
            return count($value) >= (int) $min;
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
        $min = $this->getFirstParameter();
        return "The {$fieldName} field must be at least {$min}.";
    }
} 