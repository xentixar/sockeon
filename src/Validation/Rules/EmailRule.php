<?php
/**
 * EmailRule
 * 
 * Validates that a value is a valid email address
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

class EmailRule extends BaseRule
{
    /**
     * Validate that a value is a valid email
     * 
     * @param mixed $value The value to validate
     * @return bool True if the value is a valid email
     */
    public function validate(mixed $value): bool
    {
        if ($this->isEmpty($value)) {
            return true; // Allow empty values, use required rule if needed
        }
        if (!is_string($value)) {
            return false;
        }
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Sanitize an email value
     * 
     * @param mixed $value The value to sanitize
     * @return string The sanitized email value
     */
    public function sanitize(mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return '';
        }
        /** @phpstan-ignore-next-line */
        $email = (string) $value;
        return strtolower(trim($email));
    }

    /**
     * Get the error message
     * 
     * @param string $fieldName The field name
     * @return string The error message
     */
    public function getMessage(string $fieldName): string
    {
        return "The {$fieldName} field must be a valid email address.";
    }
} 