<?php
/**
 * UrlRule
 * 
 * Validates that a value is a valid URL
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

class UrlRule extends BaseRule
{
    /**
     * Validate that a value is a valid URL
     * 
     * @param mixed $value The value to validate
     * @return bool True if the value is a valid URL
     */
    public function validate(mixed $value): bool
    {
        if ($this->isEmpty($value)) {
            return true; // Allow empty values, use required rule if needed
        }
        if (!is_string($value)) {
            return false;
        }
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Sanitize a URL value
     * 
     * @param mixed $value The value to sanitize
     * @return string The sanitized URL value
     */
    public function sanitize(mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return '';
        }
        $url = (string) $value;
        return trim($url);
    }

    /**
     * Get the error message
     * 
     * @param string $fieldName The field name
     * @return string The error message
     */
    public function getMessage(string $fieldName): string
    {
        return "The {$fieldName} field must be a valid URL.";
    }
} 