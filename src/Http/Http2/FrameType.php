<?php
/**
 * FrameType class
 * 
 * Defines HTTP/2 frame type constants
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http\Http2;

class FrameType
{
    public const DATA = 0x0;
    public const HEADERS = 0x1;
    public const PRIORITY = 0x2;
    public const RST_STREAM = 0x3;
    public const SETTINGS = 0x4;
    public const PUSH_PROMISE = 0x5;
    public const PING = 0x6;
    public const GOAWAY = 0x7;
    public const WINDOW_UPDATE = 0x8;
    public const CONTINUATION = 0x9;

    /**
     * Get frame type name
     * 
     * @param int $type
     * @return string
     */
    public static function getName(int $type): string
    {
        return match ($type) {
            self::DATA => 'DATA',
            self::HEADERS => 'HEADERS',
            self::PRIORITY => 'PRIORITY',
            self::RST_STREAM => 'RST_STREAM',
            self::SETTINGS => 'SETTINGS',
            self::PUSH_PROMISE => 'PUSH_PROMISE',
            self::PING => 'PING',
            self::GOAWAY => 'GOAWAY',
            self::WINDOW_UPDATE => 'WINDOW_UPDATE',
            self::CONTINUATION => 'CONTINUATION',
            default => 'UNKNOWN'
        };
    }
}
