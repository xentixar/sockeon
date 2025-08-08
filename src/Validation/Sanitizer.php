<?php
/**
 * Sanitizer class
 * 
 * Provides comprehensive sanitization helpers for user input
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Validation;

class Sanitizer
{
    /**
     * Sanitize a string value
     * 
     * @param mixed $value The value to sanitize
     * @param bool $trim Whether to trim whitespace
     * @param bool $stripTags Whether to strip HTML tags
     * @return string The sanitized string
     */
    public static function string(mixed $value, bool $trim = true, bool $stripTags = true): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        if ($stripTags) {
            $value = strip_tags($value);
        }

        if ($trim) {
            $value = trim($value);
        }

        return $value;
    }

    /**
     * Sanitize an email address
     * 
     * @param mixed $value The value to sanitize
     * @return string The sanitized email
     */
    public static function email(mixed $value): string
    {
        $email = self::string($value, true, true);
        return strtolower($email);
    }

    /**
     * Sanitize a URL
     * 
     * @param mixed $value The value to sanitize
     * @return string The sanitized URL
     */
    public static function url(mixed $value): string
    {
        $url = self::string($value, true, true);
        
        if (!empty($url) && !preg_match('/^https?:\/\//', $url)) {
            $url = 'http://' . $url;
        }
        
        return $url;
    }

    /**
     * Sanitize an integer value
     * 
     * @param mixed $value The value to sanitize
     * @param int $default Default value if conversion fails
     * @return int The sanitized integer
     */
    public static function integer(mixed $value, int $default = 0): int
    {
        if ($value === null) {
            return $default;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = trim($value);
            if (ctype_digit($value)) {
                return (int) $value;
            }
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * Sanitize a float value
     * 
     * @param mixed $value The value to sanitize
     * @param float $default Default value if conversion fails
     * @return float The sanitized float
     */
    public static function float(mixed $value, float $default = 0.0): float
    {
        if ($value === null) {
            return $default;
        }

        if (is_float($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    /**
     * Sanitize a boolean value
     * 
     * @param mixed $value The value to sanitize
     * @return bool The sanitized boolean
     */
    public static function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['true', '1', 'yes', 'on'], true);
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return false;
    }

    /**
     * Sanitize an array value
     * 
     * @param mixed $value The value to sanitize
     * @return array The sanitized array
     */
    public static function array(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Sanitize HTML content
     * 
     * @param mixed $value The value to sanitize
     * @param array<string> $allowedTags Allowed HTML tags
     * @return string The sanitized HTML
     */
    public static function html(mixed $value, array $allowedTags = []): string
    {
        $value = self::string($value, true, false);

        if (empty($allowedTags)) {
            return strip_tags($value);
        }

        $allowedTagsString = '<' . implode('><', $allowedTags) . '>';
        return strip_tags($value, $allowedTagsString);
    }

    /**
     * Sanitize a filename
     * 
     * @param mixed $value The value to sanitize
     * @return string The sanitized filename
     */
    public static function filename(mixed $value): string
    {
        $filename = self::string($value, true, true);
        
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        $filename = basename($filename);
        
        return $filename;
    }

    /**
     * Sanitize a phone number
     * 
     * @param mixed $value The value to sanitize
     * @return string The sanitized phone number
     */
    public static function phone(mixed $value): string
    {
        $phone = self::string($value, true, true);
        
        // Remove all non-digit characters except +, -, (, ), and space
        $phone = preg_replace('/[^0-9+\-() ]/', '', $phone);
        
        return trim($phone);
    }

    /**
     * Sanitize a credit card number
     * 
     * @param mixed $value The value to sanitize
     * @return string The sanitized credit card number
     */
    public static function creditCard(mixed $value): string
    {
        $card = self::string($value, true, true);
        
        // Remove all non-digit characters
        $card = preg_replace('/[^0-9]/', '', $card);
        
        return $card;
    }

    /**
     * Sanitize a password (basic sanitization)
     * 
     * @param mixed $value The value to sanitize
     * @return string The sanitized password
     */
    public static function password(mixed $value): string
    {
        return self::string($value, true, true);
    }

    /**
     * Sanitize JSON data
     * 
     * @param mixed $value The value to sanitize
     * @return mixed The sanitized JSON data
     */
    public static function json(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    /**
     * Sanitize an IP address
     * 
     * @param mixed $value The value to sanitize
     * @return string The sanitized IP address
     */
    public static function ipAddress(mixed $value): string
    {
        $ip = self::string($value, true, true);
        
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        
        return '';
    }

    /**
     * Sanitize a date string
     * 
     * @param mixed $value The value to sanitize
     * @param string $format The expected date format
     * @return string The sanitized date
     */
    public static function date(mixed $value, string $format = 'Y-m-d'): string
    {
        $date = self::string($value, true, true);
        
        if (empty($date)) {
            return '';
        }
        
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '';
        }
        
        return date($format, $timestamp);
    }

    /**
     * Sanitize a time string
     * 
     * @param mixed $value The value to sanitize
     * @param string $format The expected time format
     * @return string The sanitized time
     */
    public static function time(mixed $value, string $format = 'H:i:s'): string
    {
        $time = self::string($value, true, true);
        
        if (empty($time)) {
            return '';
        }
        
        $timestamp = strtotime($time);
        if ($timestamp === false) {
            return '';
        }
        
        return date($format, $timestamp);
    }

    /**
     * Sanitize a datetime string
     * 
     * @param mixed $value The value to sanitize
     * @param string $format The expected datetime format
     * @return string The sanitized datetime
     */
    public static function datetime(mixed $value, string $format = 'Y-m-d H:i:s'): string
    {
        $datetime = self::string($value, true, true);
        
        if (empty($datetime)) {
            return '';
        }
        
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return '';
        }
        
        return date($format, $timestamp);
    }

    /**
     * Sanitize a color value (hex, rgb, rgba)
     * 
     * @param mixed $value The value to sanitize
     * @return string The sanitized color
     */
    public static function color(mixed $value): string
    {
        $color = self::string($value, true, true);
        
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return $color;
        }
        
        if (preg_match('/^rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)$/', $color)) {
            return $color;
        }
        
        if (preg_match('/^rgba\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*,\s*[0-9.]+\s*\)$/', $color)) {
            return $color;
        }
        
        return '';
    }

    /**
     * Sanitize a CSS class name
     * 
     * @param mixed $value The value to sanitize
     * @return string The sanitized CSS class
     */
    public static function cssClass(mixed $value): string
    {
        $class = self::string($value, true, true);
        
        $class = preg_replace('/[^a-zA-Z0-9_-]/', '', $class);
        
        return $class;
    }

    /**
     * Sanitize an ID attribute
     * 
     * @param mixed $value The value to sanitize
     * @return string The sanitized ID
     */
    public static function id(mixed $value): string
    {
        $id = self::string($value, true, true);
        
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
        
        if (!empty($id) && !ctype_alpha($id[0])) {
            $id = 'id_' . $id;
        }
        
        return $id;
    }
} 