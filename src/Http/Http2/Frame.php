<?php
/**
 * Frame class
 * 
 * Represents an HTTP/2 frame with methods for parsing and creation
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http\Http2;

class Frame
{
    /**
     * Frame length
     * @var int
     */
    protected int $length;

    /**
     * Frame type
     * @var int
     */
    protected int $type;

    /**
     * Frame flags
     * @var int
     */
    protected int $flags;

    /**
     * Stream identifier
     * @var int
     */
    protected int $streamId;

    /**
     * Frame payload
     * @var string
     */
    protected string $payload;

    // Frame flags
    public const FLAG_END_STREAM = 0x1;
    public const FLAG_ACK = 0x1;
    public const FLAG_END_HEADERS = 0x4;
    public const FLAG_PADDED = 0x8;
    public const FLAG_PRIORITY = 0x20;

    /**
     * Constructor
     * 
     * @param int $length
     * @param int $type
     * @param int $flags
     * @param int $streamId
     * @param string $payload
     */
    public function __construct(int $length, int $type, int $flags, int $streamId, string $payload = '')
    {
        $this->length = $length;
        $this->type = $type;
        $this->flags = $flags;
        $this->streamId = $streamId;
        $this->payload = $payload;
    }

    /**
     * Parse frame from raw data
     * 
     * @param string $data
     * @param int $offset
     * @return Frame|null
     */
    public static function parse(string $data, int &$offset): ?Frame
    {
        if (strlen($data) - $offset < 9) {
            return null;
        }

        // Parse frame header (9 bytes)
        $header = substr($data, $offset, 9);
        
        // Length (24 bits)
        $length = (ord($header[0]) << 16) | (ord($header[1]) << 8) | ord($header[2]);
        
        // Type (8 bits)
        $type = ord($header[3]);
        
        // Flags (8 bits)
        $flags = ord($header[4]);
        
        // Stream ID (31 bits, R bit reserved)
        $streamId = unpack('N', substr($header, 5, 4))[1] & 0x7FFFFFFF;

        // Check if we have enough data for the payload
        if (strlen($data) - $offset < 9 + $length) {
            return null;
        }

        // Extract payload
        $payload = $length > 0 ? substr($data, $offset + 9, $length) : '';

        return new self($length, $type, $flags, $streamId, $payload);
    }

    /**
     * Convert frame to binary string
     * 
     * @return string
     */
    public function toBinary(): string
    {
        // Frame header (9 bytes)
        $header = '';
        
        // Length (24 bits)
        $header .= chr(($this->length >> 16) & 0xFF);
        $header .= chr(($this->length >> 8) & 0xFF);
        $header .= chr($this->length & 0xFF);
        
        // Type (8 bits)
        $header .= chr($this->type);
        
        // Flags (8 bits)
        $header .= chr($this->flags);
        
        // Stream ID (31 bits with R bit = 0)
        $header .= pack('N', $this->streamId & 0x7FFFFFFF);

        return $header . $this->payload;
    }

    /**
     * Create DATA frame
     * 
     * @param int $streamId
     * @param string $data
     * @param bool $endStream
     * @return Frame
     */
    public static function createData(int $streamId, string $data, bool $endStream = false): Frame
    {
        $flags = $endStream ? self::FLAG_END_STREAM : 0;
        return new self(strlen($data), FrameType::DATA, $flags, $streamId, $data);
    }

    /**
     * Create HEADERS frame
     * 
     * @param int $streamId
     * @param string $headerBlock
     * @param bool $endHeaders
     * @param bool $endStream
     * @return Frame
     */
    public static function createHeaders(int $streamId, string $headerBlock, bool $endHeaders = true, bool $endStream = false): Frame
    {
        $flags = 0;
        if ($endHeaders) $flags |= self::FLAG_END_HEADERS;
        if ($endStream) $flags |= self::FLAG_END_STREAM;
        
        return new self(strlen($headerBlock), FrameType::HEADERS, $flags, $streamId, $headerBlock);
    }

    /**
     * Create SETTINGS frame
     * 
     * @param array $settings
     * @param bool $ack
     * @return Frame
     */
    public static function createSettings(array $settings = [], bool $ack = false): Frame
    {
        $payload = '';
        
        if (!$ack) {
            foreach ($settings as $id => $value) {
                $payload .= pack('nN', $id, $value);
            }
        }

        $flags = $ack ? self::FLAG_ACK : 0;
        return new self(strlen($payload), FrameType::SETTINGS, $flags, 0, $payload);
    }

    /**
     * Create SETTINGS ACK frame
     * 
     * @return Frame
     */
    public static function createSettingsAck(): Frame
    {
        return self::createSettings([], true);
    }

    /**
     * Create RST_STREAM frame
     * 
     * @param int $streamId
     * @param int $errorCode
     * @return Frame
     */
    public static function createRstStream(int $streamId, int $errorCode): Frame
    {
        $payload = pack('N', $errorCode);
        return new self(4, FrameType::RST_STREAM, 0, $streamId, $payload);
    }

    /**
     * Create PING frame
     * 
     * @param string $data
     * @param bool $ack
     * @return Frame
     */
    public static function createPing(string $data = '', bool $ack = false): Frame
    {
        // PING data must be exactly 8 bytes
        $data = str_pad(substr($data, 0, 8), 8, "\0");
        $flags = $ack ? self::FLAG_ACK : 0;
        return new self(8, FrameType::PING, $flags, 0, $data);
    }

    /**
     * Create PING ACK frame
     * 
     * @param string $data
     * @return Frame
     */
    public static function createPingAck(string $data): Frame
    {
        return self::createPing($data, true);
    }

    /**
     * Create GOAWAY frame
     * 
     * @param int $lastStreamId
     * @param int $errorCode
     * @param string $debugData
     * @return Frame
     */
    public static function createGoAway(int $lastStreamId, int $errorCode, string $debugData = ''): Frame
    {
        $payload = pack('NN', $lastStreamId & 0x7FFFFFFF, $errorCode) . $debugData;
        return new self(strlen($payload), FrameType::GOAWAY, 0, 0, $payload);
    }

    /**
     * Create WINDOW_UPDATE frame
     * 
     * @param int $streamId
     * @param int $increment
     * @return Frame
     */
    public static function createWindowUpdate(int $streamId, int $increment): Frame
    {
        $payload = pack('N', $increment & 0x7FFFFFFF);
        return new self(4, FrameType::WINDOW_UPDATE, 0, $streamId, $payload);
    }

    /**
     * Create PUSH_PROMISE frame
     * 
     * @param int $streamId
     * @param int $promisedStreamId
     * @param string $headerBlock
     * @return Frame
     */
    public static function createPushPromise(int $streamId, int $promisedStreamId, string $headerBlock): Frame
    {
        $payload = pack('N', $promisedStreamId & 0x7FFFFFFF) . $headerBlock;
        return new self(strlen($payload), FrameType::PUSH_PROMISE, self::FLAG_END_HEADERS, $streamId, $payload);
    }

    /**
     * Get frame length
     * 
     * @return int
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * Get frame type
     * 
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * Get frame flags
     * 
     * @return int
     */
    public function getFlags(): int
    {
        return $this->flags;
    }

    /**
     * Get stream ID
     * 
     * @return int
     */
    public function getStreamId(): int
    {
        return $this->streamId;
    }

    /**
     * Get frame payload
     * 
     * @return string
     */
    public function getPayload(): string
    {
        return $this->payload;
    }

    /**
     * Check if END_STREAM flag is set
     * 
     * @return bool
     */
    public function hasEndStreamFlag(): bool
    {
        return ($this->flags & self::FLAG_END_STREAM) !== 0;
    }

    /**
     * Check if ACK flag is set
     * 
     * @return bool
     */
    public function hasAckFlag(): bool
    {
        return ($this->flags & self::FLAG_ACK) !== 0;
    }

    /**
     * Check if END_HEADERS flag is set
     * 
     * @return bool
     */
    public function hasEndHeadersFlag(): bool
    {
        return ($this->flags & self::FLAG_END_HEADERS) !== 0;
    }

    /**
     * Check if PADDED flag is set
     * 
     * @return bool
     */
    public function hasPaddedFlag(): bool
    {
        return ($this->flags & self::FLAG_PADDED) !== 0;
    }

    /**
     * Check if PRIORITY flag is set
     * 
     * @return bool
     */
    public function hasPriorityFlag(): bool
    {
        return ($this->flags & self::FLAG_PRIORITY) !== 0;
    }
}
