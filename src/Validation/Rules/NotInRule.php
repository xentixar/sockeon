<?php

/**
 * NotInRule
 *
 * Validates that a value is not in a list of forbidden values
 *
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

class NotInRule extends BaseRule
{
    /**
     * Validate that a value is not in the forbidden list
     *
     * @param mixed $value The value to validate
     * @return bool True if the value is not in the forbidden list
     */
    public function validate(mixed $value): bool
    {
        if ($this->isEmpty($value)) {
            return true; // Allow empty values, use required rule if needed
        }

        return !in_array($value, $this->parameters, true);
    }

    /**
     * Get the error message
     *
     * @param string $fieldName The field name
     * @return string The error message
     */
    public function getMessage(string $fieldName): string
    {
        $forbidden = implode(', ', $this->parameters);
        return "The {$fieldName} field must not be one of: {$forbidden}.";
    }
}
