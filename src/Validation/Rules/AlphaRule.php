<?php

/**
 * AlphaRule
 *
 * Validates that a value contains only alphabetic characters
 *
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

class AlphaRule extends BaseRule
{
    /**
     * Validate that a value contains only alphabetic characters
     *
     * @param mixed $value The value to validate
     * @return bool True if the value contains only alphabetic characters
     */
    public function validate(mixed $value): bool
    {
        if ($this->isEmpty($value)) {
            return true; // Allow empty values, use required rule if needed
        }

        if (!is_string($value)) {
            return false;
        }

        return ctype_alpha($value);
    }

    /**
     * Sanitize an alpha value
     *
     * @param mixed $value The value to sanitize
     * @return string The sanitized alpha value
     */
    public function sanitize(mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return '';
        }
        /** @phpstan-ignore-next-line */
        $value = (string) $value;
        $result = preg_replace('/[^a-zA-Z]/', '', $value);
        return $result !== null ? $result : '';
    }

    /**
     * Get the error message
     *
     * @param string $fieldName The field name
     * @return string The error message
     */
    public function getMessage(string $fieldName): string
    {
        return "The {$fieldName} field must contain only alphabetic characters.";
    }
}
