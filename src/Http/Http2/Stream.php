<?php
/**
 * Stream class
 * 
 * Represents an HTTP/2 stream with state management
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http\Http2;

class Stream
{
    /**
     * Stream states
     */
    public const STATE_IDLE = 'idle';
    public const STATE_RESERVED_LOCAL = 'reserved_local';
    public const STATE_RESERVED_REMOTE = 'reserved_remote';
    public const STATE_OPEN = 'open';
    public const STATE_HALF_CLOSED_LOCAL = 'half_closed_local';
    public const STATE_HALF_CLOSED_REMOTE = 'half_closed_remote';
    public const STATE_CLOSED = 'closed';

    /**
     * Stream identifier
     * @var int
     */
    protected int $id;

    /**
     * Stream state
     * @var string
     */
    protected string $state;

    /**
     * Stream headers
     * @var array<string, string>
     */
    protected array $headers = [];

    /**
     * Stream data
     * @var string
     */
    protected string $data = '';

    /**
     * Send window size
     * @var int
     */
    protected int $sendWindow = 65535;

    /**
     * Receive window size
     * @var int
     */
    protected int $receiveWindow = 65535;

    /**
     * Whether headers are complete
     * @var bool
     */
    protected bool $headersComplete = false;

    /**
     * Stream priority weight
     * @var int
     */
    protected int $weight = 16;

    /**
     * Parent stream ID for priority
     * @var int|null
     */
    protected ?int $dependsOn = null;

    /**
     * Constructor
     * 
     * @param int $id
     */
    public function __construct(int $id)
    {
        $this->id = $id;
        $this->state = self::STATE_IDLE;
    }

    /**
     * Get stream ID
     * 
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get stream state
     * 
     * @return string
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Set stream state
     * 
     * @param string $state
     * @return void
     */
    public function setState(string $state): void
    {
        $this->state = $state;
    }

    /**
     * Add headers to stream
     * 
     * @param array<string, string> $headers
     * @return void
     */
    public function addHeaders(array $headers): void
    {
        $this->headers = array_merge($this->headers, $headers);
        $this->setState(self::STATE_OPEN);
    }

    /**
     * Mark headers as complete
     * 
     * @return void
     */
    public function setHeadersComplete(): void
    {
        $this->headersComplete = true;
    }

    /**
     * Check if headers are complete
     * 
     * @return bool
     */
    public function hasCompleteHeaders(): bool
    {
        return $this->headersComplete;
    }

    /**
     * Get stream headers
     * 
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Add data to stream
     * 
     * @param string $data
     * @return void
     */
    public function addData(string $data): void
    {
        $this->data .= $data;
    }

    /**
     * Get stream data
     * 
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * Update send window size
     * 
     * @param int $increment
     * @return void
     */
    public function updateSendWindow(int $increment): void
    {
        $this->sendWindow += $increment;
    }

    /**
     * Update receive window size
     * 
     * @param int $increment
     * @return void
     */
    public function updateReceiveWindow(int $increment): void
    {
        $this->receiveWindow += $increment;
    }

    /**
     * Get send window size
     * 
     * @return int
     */
    public function getSendWindow(): int
    {
        return $this->sendWindow;
    }

    /**
     * Get receive window size
     * 
     * @return int
     */
    public function getReceiveWindow(): int
    {
        return $this->receiveWindow;
    }

    /**
     * Consume send window
     * 
     * @param int $bytes
     * @return bool True if window allows sending
     */
    public function consumeSendWindow(int $bytes): bool
    {
        if ($this->sendWindow >= $bytes) {
            $this->sendWindow -= $bytes;
            return true;
        }
        return false;
    }

    /**
     * Consume receive window
     * 
     * @param int $bytes
     * @return bool True if window allows receiving
     */
    public function consumeReceiveWindow(int $bytes): bool
    {
        if ($this->receiveWindow >= $bytes) {
            $this->receiveWindow -= $bytes;
            return true;
        }
        return false;
    }

    /**
     * Set stream priority
     * 
     * @param int $weight
     * @param int|null $dependsOn
     * @return void
     */
    public function setPriority(int $weight, ?int $dependsOn = null): void
    {
        $this->weight = max(1, min(256, $weight));
        $this->dependsOn = $dependsOn;
    }

    /**
     * Get stream weight
     * 
     * @return int
     */
    public function getWeight(): int
    {
        return $this->weight;
    }

    /**
     * Get parent stream ID
     * 
     * @return int|null
     */
    public function getDependsOn(): ?int
    {
        return $this->dependsOn;
    }

    /**
     * Check if stream can send data
     * 
     * @return bool
     */
    public function canSend(): bool
    {
        return in_array($this->state, [
            self::STATE_OPEN,
            self::STATE_HALF_CLOSED_REMOTE
        ]);
    }

    /**
     * Check if stream can receive data
     * 
     * @return bool
     */
    public function canReceive(): bool
    {
        return in_array($this->state, [
            self::STATE_OPEN,
            self::STATE_HALF_CLOSED_LOCAL
        ]);
    }

    /**
     * Check if stream is closed
     * 
     * @return bool
     */
    public function isClosed(): bool
    {
        return $this->state === self::STATE_CLOSED;
    }

    /**
     * Reset stream
     * 
     * @return void
     */
    public function reset(): void
    {
        $this->setState(self::STATE_CLOSED);
        $this->headers = [];
        $this->data = '';
        $this->headersComplete = false;
    }
}
