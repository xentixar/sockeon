<?php

/**
 * BetweenRule
 *
 * Validates that a value is between two values
 *
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

class BetweenRule extends BaseRule
{
    /**
     * Validate that a value is between min and max
     *
     * @param mixed $value The value to validate
     * @return bool True if the value is between min and max
     */
    public function validate(mixed $value): bool
    {
        if ($this->isEmpty($value)) {
            return true; // Allow empty values, use required rule if needed
        }

        $min = $this->getFirstParameter();
        $max = $this->getSecondParameter();

        if ($min === null || $max === null) {
            return true;
        }

        if (is_numeric($value)) {
            $value = (float) $value;
            return $value >= (float) $min && $value <= (float) $max;
        }

        if (is_string($value)) {
            $length = mb_strlen($value);
            return $length >= (int) $min && $length <= (int) $max;
        }

        if (is_array($value)) {
            $count = count($value);
            return $count >= (int) $min && $count <= (int) $max;
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
        $max = $this->getSecondParameter();
        return "The {$fieldName} field must be between {$min} and {$max}.";
    }
}
