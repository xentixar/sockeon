<?php
/**
 * RuleInterface
 * 
 * Interface that all validation rules must implement
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

interface RuleInterface
{
    /**
     * Validate a value
     * 
     * @param mixed $value The value to validate
     * @return bool True if validation passes
     */
    public function validate(mixed $value): bool;

    /**
     * Sanitize a value
     * 
     * @param mixed $value The value to sanitize
     * @return mixed The sanitized value
     */
    public function sanitize(mixed $value): mixed;

    /**
     * Get the error message for this rule
     * 
     * @param string $fieldName The field name for the error message
     * @return string The error message
     */
    public function getMessage(string $fieldName): string;
} 