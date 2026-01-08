<?php

/**
 * Validator class
 *
 * Provides comprehensive validation for incoming WebSocket and HTTP data
 * with schema validation and sanitization helpers
 *
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation;

use InvalidArgumentException;
use Sockeon\Sockeon\Exception\Validation\ValidationException;
use Sockeon\Sockeon\Validation\Rules\RuleInterface;
use Sockeon\Sockeon\Validation\Rules\Required;
use Sockeon\Sockeon\Validation\Rules\StringRule;
use Sockeon\Sockeon\Validation\Rules\IntegerRule;
use Sockeon\Sockeon\Validation\Rules\FloatRule;
use Sockeon\Sockeon\Validation\Rules\BooleanRule;
use Sockeon\Sockeon\Validation\Rules\ArrayRule;
use Sockeon\Sockeon\Validation\Rules\EmailRule;
use Sockeon\Sockeon\Validation\Rules\UrlRule;
use Sockeon\Sockeon\Validation\Rules\MinRule;
use Sockeon\Sockeon\Validation\Rules\MaxRule;
use Sockeon\Sockeon\Validation\Rules\RegexRule;
use Sockeon\Sockeon\Validation\Rules\InRule;
use Sockeon\Sockeon\Validation\Rules\NotInRule;
use Sockeon\Sockeon\Validation\Rules\BetweenRule;
use Sockeon\Sockeon\Validation\Rules\AlphaRule;
use Sockeon\Sockeon\Validation\Rules\AlphaNumericRule;
use Sockeon\Sockeon\Validation\Rules\NumericRule;
use Sockeon\Sockeon\Validation\Rules\JsonRule;

class Validator
{
    /**
     * Validation errors
     * @var array<string, array<int, string>>
     */
    protected array $errors = [];

    /**
     * Sanitized data
     * @var array<string, mixed>
     */
    protected array $sanitized = [];

    /**
     * Validation rules
     * @var array<string, array<int, RuleInterface>>
     */
    protected array $rules = [];

    /**
     * Custom error messages
     * @var array<string, string>
     */
    protected array $messages = [];

    /**
     * Custom field names
     * @var array<string, string>
     */
    protected array $fieldNames = [];

    /**
     * Validate data against rules
     *
     * @param array<mixed, mixed> $data The data to validate
     * @param array<string, string|array<int, string>> $rules The validation rules
     * @param array<string, string> $messages Custom error messages
     * @param array<string, string> $fieldNames Custom field names
     * @return bool True if validation passes
     * @throws ValidationException
     */
    public function validate(array $data, array $rules, array $messages = [], array $fieldNames = []): bool
    {
        $this->errors = [];
        $this->sanitized = [];
        $this->messages = $messages;
        $this->fieldNames = $fieldNames;

        foreach ($rules as $field => $fieldRules) {
            $this->validateField($field, $data[$field] ?? null, $fieldRules);
        }

        if (!empty($this->errors)) {
            throw new ValidationException('Validation failed', $this->errors);
        }

        return true;
    }

    /**
     * Validate a single field
     *
     * @param string $field The field name
     * @param mixed $value The field value
     * @param string|array<int, string> $rules The validation rules
     * @return void
     */
    protected function validateField(string $field, mixed $value, string|array $rules): void
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        $ruleObjects = [];
        foreach ($rules as $rule) {
            $ruleObjects[] = $this->createRule($rule);
        }

        $this->rules[$field] = $ruleObjects;

        foreach ($ruleObjects as $rule) {
            if (!$rule->validate($value)) {
                $message = $this->getCustomMessage($field, $rule) ?? $rule->getMessage($this->getFieldName($field));
                $this->addError($field, $message);
                break;
            }
        }

        if (!isset($this->errors[$field])) {
            $this->sanitized[$field] = $this->sanitizeValue($value, $ruleObjects);
        }
    }

    /**
     * Create a validation rule object
     *
     * @param string $rule The rule string
     * @return RuleInterface The rule object
     */
    protected function createRule(string $rule): RuleInterface
    {
        $parts = explode(':', $rule, 2);
        $ruleName = trim($parts[0]);
        $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];

        return match ($ruleName) {
            'required' => new Required(),
            'string' => new StringRule(),
            'integer', 'int' => new IntegerRule(),
            'float', 'numeric' => new FloatRule(),
            'boolean', 'bool' => new BooleanRule(),
            'array' => new ArrayRule(),
            'email' => new EmailRule(),
            'url' => new UrlRule(),
            'min' => new MinRule([$parameters[0] ?? '']),
            'max' => new MaxRule([$parameters[0] ?? '']),
            'regex' => new RegexRule([$this->parseRegexPattern($rule)]),
            'in' => new InRule($parameters),
            'not_in' => new NotInRule($parameters),
            'between' => new BetweenRule([$parameters[0] ?? '', $parameters[1] ?? '']),
            'alpha' => new AlphaRule(),
            'alpha_num' => new AlphaNumericRule(),
            'numeric' => new NumericRule(),
            'json' => new JsonRule(),
            default => throw new InvalidArgumentException("Unknown validation rule: $ruleName")
        };
    }

    /**
     * Parse regex pattern from rule string
     *
     * @param string $rule The rule string
     * @return string The regex pattern
     */
    protected function parseRegexPattern(string $rule): string
    {
        $pattern = substr($rule, 6);

        $lastDelimiter = '';
        for ($i = strlen($pattern) - 1; $i >= 0; $i--) {
            $char = $pattern[$i];
            if (in_array($char, ['/', '#', '~', '|', '!', '@', '%', '&', '=', '+', '-', ':', ';', '?', '^', '$', '*', '(', ')', '[', ']', '{', '}', '\\', '.', ',', ' '])) {
                $lastDelimiter = $char;
                break;
            }
        }

        if ($lastDelimiter === '') {
            return $pattern;
        }

        $lastPos = strrpos($pattern, $lastDelimiter);
        if ($lastPos === false) {
            return $pattern;
        }

        return substr($pattern, 0, $lastPos + 1);
    }

    /**
     * Add a validation error
     *
     * @param string $field The field name
     * @param string $message The error message
     * @return void
     */
    protected function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Get the display name for a field
     *
     * @param string $field The field name
     * @return string The display name
     */
    protected function getFieldName(string $field): string
    {
        return $this->fieldNames[$field] ?? $field;
    }

    /**
     * Get custom message for a field and rule
     *
     * @param string $field The field name
     * @param RuleInterface $rule The rule object
     * @return string|null The custom message or null
     */
    protected function getCustomMessage(string $field, RuleInterface $rule): ?string
    {
        $ruleName = $this->getRuleName($rule);
        $messageKey = "{$field}.{$ruleName}";

        return $this->messages[$messageKey] ?? null;
    }

    /**
     * Get the rule name from a rule object
     *
     * @param RuleInterface $rule The rule object
     * @return string The rule name
     */
    protected function getRuleName(RuleInterface $rule): string
    {
        $className = get_class($rule);
        $parts = explode('\\', $className);
        /** @var string|false $lastPart */
        $lastPart = end($parts);
        $className = $lastPart !== false ? $lastPart : 'unknown';

        $result = preg_replace('/(?<!^)[A-Z]/', '_$0', $className);
        $ruleName = strtolower($result !== null ? $result : $className);
        $ruleName = str_replace('_rule', '', $ruleName);

        return $ruleName;
    }

    /**
     * Sanitize a value based on validation rules
     *
     * @param mixed $value The value to sanitize
     * @param array<int, RuleInterface> $rules The validation rules
     * @return mixed The sanitized value
     */
    protected function sanitizeValue(mixed $value, array $rules): mixed
    {
        foreach ($rules as $rule) {
            $value = $rule->sanitize($value);
        }
        return $value;
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

    /**
     * Get sanitized data
     *
     * @return array<string, mixed> The sanitized data
     */
    public function getSanitized(): array
    {
        return $this->sanitized;
    }

    /**
     * Check if validation has errors
     *
     * @return bool True if there are errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get first error for a field
     *
     * @param string $field The field name
     * @return string|null The first error message or null
     */
    public function getFirstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Get all errors for a field
     *
     * @param string $field The field name
     * @return array<int, string> The error messages
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }
}
