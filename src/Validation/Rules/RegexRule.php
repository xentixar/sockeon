<?php
/**
 * RegexRule
 * 
 * Validates that a value matches a regex pattern
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

class RegexRule extends BaseRule
{
    /**
     * Validate that a value matches a regex pattern
     * 
     * @param mixed $value The value to validate
     * @return bool True if the value matches the pattern
     */
    public function validate(mixed $value): bool
    {
        if ($this->isEmpty($value)) {
            return true; // Allow empty values, use required rule if needed
        }

        $pattern = $this->getFirstParameter();
        if ($pattern === null) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        return preg_match($pattern, $value) === 1;
    }

    /**
     * Get the error message
     * 
     * @param string $fieldName The field name
     * @return string The error message
     */
    public function getMessage(string $fieldName): string
    {
        return "The {$fieldName} field format is invalid.";
    }
} 