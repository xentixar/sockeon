<?php
/**
 * StreamManagerInterface
 * 
 * Interface for HTTP/2 stream management
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Contracts\Http2;

use Sockeon\Sockeon\Http\Http2\Stream;

interface StreamManagerInterface
{
    /**
     * Create a new stream
     * 
     * @param int $streamId
     * @return Stream
     */
    public function createStream(int $streamId): Stream;

    /**
     * Get a stream by ID
     * 
     * @param int $streamId
     * @return Stream|null
     */
    public function getStream(int $streamId): ?Stream;

    /**
     * Close a stream
     * 
     * @param int $streamId
     * @return void
     */
    public function closeStream(int $streamId): void;

    /**
     * Get all active streams
     * 
     * @return array<int, Stream>
     */
    public function getStreams(): array;

    /**
     * Get number of active streams
     * 
     * @return int
     */
    public function getActiveStreamCount(): int;

    /**
     * Set maximum concurrent streams
     * 
     * @param int $max
     * @return void
     */
    public function setMaxConcurrentStreams(int $max): void;

    /**
     * Get maximum concurrent streams
     * 
     * @return int
     */
    public function getMaxConcurrentStreams(): int;
}
