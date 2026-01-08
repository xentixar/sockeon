<?php

/**
 * BaseRule class
 *
 * Base class for all validation rules providing common functionality
 *
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation\Rules;

abstract class BaseRule implements RuleInterface
{
    /**
     * Rule parameters
     * @var array<int, string>
     */
    protected array $parameters;

    /**
     * Constructor
     *
     * @param array<int, string> $parameters Rule parameters
     */
    public function __construct(array $parameters = [])
    {
        $this->parameters = $parameters;
    }

    /**
     * Sanitize a value (default implementation)
     *
     * @param mixed $value The value to sanitize
     * @return mixed The sanitized value
     */
    public function sanitize(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Check if a value is empty
     *
     * @param mixed $value The value to check
     * @return bool True if the value is empty
     */
    protected function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }

    /**
     * Get the first parameter
     *
     * @return string|null The first parameter or null
     */
    protected function getFirstParameter(): ?string
    {
        return $this->parameters[0] ?? null;
    }

    /**
     * Get the second parameter
     *
     * @return string|null The second parameter or null
     */
    protected function getSecondParameter(): ?string
    {
        return $this->parameters[1] ?? null;
    }
}
