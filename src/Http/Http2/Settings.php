<?php
/**
 * Settings class
 * 
 * Manages HTTP/2 connection settings
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http\Http2;

use InvalidArgumentException;

class Settings
{
    /**
     * Setting identifiers
     */
    public const HEADER_TABLE_SIZE = 0x1;
    public const ENABLE_PUSH = 0x2;
    public const MAX_CONCURRENT_STREAMS = 0x3;
    public const INITIAL_WINDOW_SIZE = 0x4;
    public const MAX_FRAME_SIZE = 0x5;
    public const MAX_HEADER_LIST_SIZE = 0x6;

    /**
     * Current settings
     * @var array<int, int>
     */
    protected array $settings = [
        self::HEADER_TABLE_SIZE => 4096,
        self::ENABLE_PUSH => 1,
        self::MAX_CONCURRENT_STREAMS => 100,
        self::INITIAL_WINDOW_SIZE => 65535,
        self::MAX_FRAME_SIZE => 16384,
        self::MAX_HEADER_LIST_SIZE => 8192
    ];

    /**
     * Get setting value
     * 
     * @param int $id
     * @return int|null
     */
    public function get(int $id): ?int
    {
        return $this->settings[$id] ?? null;
    }

    /**
     * Set setting value
     * 
     * @param int $id
     * @param int $value
     * @return void
     */
    public function set(int $id, int $value): void
    {
        $this->settings[$id] = $value;
    }

    /**
     * Get all settings
     * 
     * @return array<int, int>
     */
    public function getAll(): array
    {
        return $this->settings;
    }

    /**
     * Process SETTINGS frame
     * 
     * @param Frame $frame
     * @return void
     */
    public function processSettingsFrame(Frame $frame): void
    {
        if ($frame->hasAckFlag()) {
            return; // ACK frame, nothing to process
        }

        $payload = $frame->getPayload();
        $length = strlen($payload);

        // Each setting is 6 bytes (2 bytes ID + 4 bytes value)
        for ($i = 0; $i < $length; $i += 6) {
            if ($i + 6 > $length) {
                break;
            }

            $settingData = substr($payload, $i, 6);
            $id = unpack('n', substr($settingData, 0, 2))[1];
            $value = unpack('N', substr($settingData, 2, 4))[1];

            $this->validateAndSetSetting($id, $value);
        }
    }

    /**
     * Validate and set setting
     * 
     * @param int $id
     * @param int $value
     * @return void
     * @throws InvalidArgumentException
     */
    protected function validateAndSetSetting(int $id, int $value): void
    {
        switch ($id) {
            case self::HEADER_TABLE_SIZE:
                // No validation needed
                break;

            case self::ENABLE_PUSH:
                if ($value !== 0 && $value !== 1) {
                    throw new InvalidArgumentException('ENABLE_PUSH must be 0 or 1');
                }
                break;

            case self::MAX_CONCURRENT_STREAMS:
                // No validation needed
                break;

            case self::INITIAL_WINDOW_SIZE:
                if ($value > 0x7FFFFFFF) {
                    throw new InvalidArgumentException('INITIAL_WINDOW_SIZE exceeds maximum');
                }
                break;

            case self::MAX_FRAME_SIZE:
                if ($value < 16384 || $value > 16777215) {
                    throw new InvalidArgumentException('MAX_FRAME_SIZE out of range');
                }
                break;

            case self::MAX_HEADER_LIST_SIZE:
                // No validation needed
                break;

            default:
                // Unknown settings are ignored per RFC 7540
                return;
        }

        $this->settings[$id] = $value;
    }

    /**
     * Create SETTINGS frame
     * 
     * @param array<int, int> $settings Optional settings to override defaults
     * @return string
     */
    public function createSettingsFrame(array $settings = []): string
    {
        $settingsToSend = array_merge($this->settings, $settings);
        return Frame::createSettings($settingsToSend)->toBinary();
    }

    /**
     * Check if server push is enabled
     * 
     * @return bool
     */
    public function isPushEnabled(): bool
    {
        return $this->get(self::ENABLE_PUSH) === 1;
    }

    /**
     * Get header table size
     * 
     * @return int
     */
    public function getHeaderTableSize(): int
    {
        return $this->get(self::HEADER_TABLE_SIZE) ?? 4096;
    }

    /**
     * Get max concurrent streams
     * 
     * @return int
     */
    public function getMaxConcurrentStreams(): int
    {
        return $this->get(self::MAX_CONCURRENT_STREAMS) ?? 100;
    }

    /**
     * Get initial window size
     * 
     * @return int
     */
    public function getInitialWindowSize(): int
    {
        return $this->get(self::INITIAL_WINDOW_SIZE) ?? 65535;
    }

    /**
     * Get max frame size
     * 
     * @return int
     */
    public function getMaxFrameSize(): int
    {
        return $this->get(self::MAX_FRAME_SIZE) ?? 16384;
    }

    /**
     * Get max header list size
     * 
     * @return int
     */
    public function getMaxHeaderListSize(): int
    {
        return $this->get(self::MAX_HEADER_LIST_SIZE) ?? 8192;
    }
}
