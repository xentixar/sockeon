<?php

/**
 * ValidationException class
 *
 * Exception thrown when validation fails
 *
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Exception\Validation;

use Exception;

class ValidationException extends Exception
{
    /**
     * Validation errors
     * @var array<string, array<int, string>>
     */
    protected array $errors;

    /**
     * Constructor
     *
     * @param string $message The exception message
     * @param array<string, array<int, string>> $errors The validation errors
     * @param int $code The exception code
     * @param Exception|null $previous The previous exception
     */
    public function __construct(string $message = '', array $errors = [], int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Get validation errors
     *
     * @return array<string, array<int, string>> The validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
