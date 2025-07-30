<?php
/**
 * StreamManager class
 * 
 * Manages HTTP/2 streams and their lifecycle
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http\Http2;

use Sockeon\Sockeon\Contracts\Http2\StreamManagerInterface;

class StreamManager implements StreamManagerInterface
{
    /**
     * Active streams
     * @var array<int, Stream>
     */
    protected array $streams = [];

    /**
     * Last stream ID used
     * @var int
     */
    protected int $lastStreamId = 0;

    /**
     * Next stream ID for server-initiated streams
     * @var int
     */
    protected int $nextServerStreamId = 2;

    /**
     * Maximum number of concurrent streams
     * @var int
     */
    protected int $maxConcurrentStreams = 100;

    /**
     * Create a new stream
     * 
     * @param int $streamId
     * @return Stream
     */
    public function createStream(int $streamId): Stream
    {
        if (isset($this->streams[$streamId])) {
            throw new \InvalidArgumentException("Stream {$streamId} already exists");
        }

        if (count($this->streams) >= $this->maxConcurrentStreams) {
            throw new \RuntimeException("Maximum concurrent streams exceeded");
        }

        $stream = new Stream($streamId);
        $this->streams[$streamId] = $stream;

        if ($streamId > $this->lastStreamId) {
            $this->lastStreamId = $streamId;
        }

        return $stream;
    }

    /**
     * Get a stream by ID
     * 
     * @param int $streamId
     * @return Stream|null
     */
    public function getStream(int $streamId): ?Stream
    {
        return $this->streams[$streamId] ?? null;
    }

    /**
     * Get all active streams
     * 
     * @return array<int, Stream>
     */
    public function getStreams(): array
    {
        return $this->streams;
    }

    /**
     * Close a stream
     * 
     * @param int $streamId
     * @return void
     */
    public function closeStream(int $streamId): void
    {
        if (isset($this->streams[$streamId])) {
            $this->streams[$streamId]->setState(Stream::STATE_CLOSED);
            unset($this->streams[$streamId]);
        }
    }

    /**
     * Close all streams with ID greater than specified
     * 
     * @param int $lastStreamId
     * @return void
     */
    public function closeStreamsAfter(int $lastStreamId): void
    {
        foreach ($this->streams as $streamId => $stream) {
            if ($streamId > $lastStreamId) {
                $this->closeStream($streamId);
            }
        }
    }

    /**
     * Get the last stream ID
     * 
     * @return int
     */
    public function getLastStreamId(): int
    {
        return $this->lastStreamId;
    }

    /**
     * Get the next stream ID for server-initiated streams
     * 
     * @return int
     */
    public function getNextStreamId(): int
    {
        $streamId = $this->nextServerStreamId;
        $this->nextServerStreamId += 2; // Server streams are even
        return $streamId;
    }

    /**
     * Set maximum concurrent streams
     * 
     * @param int $max
     * @return void
     */
    public function setMaxConcurrentStreams(int $max): void
    {
        $this->maxConcurrentStreams = $max;
    }

    /**
     * Get maximum concurrent streams
     * 
     * @return int
     */
    public function getMaxConcurrentStreams(): int
    {
        return $this->maxConcurrentStreams;
    }

    /**
     * Get number of active streams
     * 
     * @return int
     */
    public function getActiveStreamCount(): int
    {
        return count($this->streams);
    }

    /**
     * Clean up closed streams
     * 
     * @return void
     */
    public function cleanup(): void
    {
        foreach ($this->streams as $streamId => $stream) {
            if ($stream->isClosed()) {
                unset($this->streams[$streamId]);
            }
        }
    }

    /**
     * Reset all streams
     * 
     * @return void
     */
    public function reset(): void
    {
        foreach ($this->streams as $stream) {
            $stream->reset();
        }
        $this->streams = [];
        $this->lastStreamId = 0;
        $this->nextServerStreamId = 2;
    }

    /**
     * Check if stream ID is valid for client-initiated stream
     * 
     * @param int $streamId
     * @return bool
     */
    public function isValidClientStreamId(int $streamId): bool
    {
        return $streamId > 0 && ($streamId % 2) === 1;
    }

    /**
     * Check if stream ID is valid for server-initiated stream
     * 
     * @param int $streamId
     * @return bool
     */
    public function isValidServerStreamId(int $streamId): bool
    {
        return $streamId > 0 && ($streamId % 2) === 0;
    }

    /**
     * Get streams by state
     * 
     * @param string $state
     * @return array<int, Stream>
     */
    public function getStreamsByState(string $state): array
    {
        return array_filter($this->streams, fn(Stream $stream) => $stream->getState() === $state);
    }

    /**
     * Get streams that can send data
     * 
     * @return array<int, Stream>
     */
    public function getSendableStreams(): array
    {
        return array_filter($this->streams, fn(Stream $stream) => $stream->canSend());
    }

    /**
     * Get streams that can receive data
     * 
     * @return array<int, Stream>
     */
    public function getReceivableStreams(): array
    {
        return array_filter($this->streams, fn(Stream $stream) => $stream->canReceive());
    }
}
