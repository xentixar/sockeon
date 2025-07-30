<?php
/**
 * Http2Request class
 * 
 * Extends the base Request class with HTTP/2 specific functionality
 * including server push capabilities
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http\Http2;

use Sockeon\Sockeon\Http\Request;
use Sockeon\Sockeon\Http\Http2\ServerPush;

class Http2Request extends Request
{
    /**
     * HTTP/2 stream ID
     * @var int
     */
    protected int $streamId;

    /**
     * Server push instance
     * @var ServerPush|null
     */
    protected ?ServerPush $serverPush = null;

    /**
     * Constructor
     * 
     * @param array<string, mixed> $requestData The parsed HTTP request data
     * @param int $streamId The HTTP/2 stream ID
     */
    public function __construct(array $requestData, int $streamId)
    {
        parent::__construct($requestData);
        $this->streamId = $streamId;
    }

    /**
     * Get HTTP/2 stream ID
     * 
     * @return int
     */
    public function getStreamId(): int
    {
        return $this->streamId;
    }

    /**
     * Set server push instance
     * 
     * @param ServerPush $serverPush
     * @return void
     */
    public function setServerPush(ServerPush $serverPush): void
    {
        $this->serverPush = $serverPush;
    }

    /**
     * Get server push instance
     * 
     * @return ServerPush|null
     */
    public function getServerPush(): ?ServerPush
    {
        return $this->serverPush;
    }

    /**
     * Check if server push is available
     * 
     * @return bool
     */
    public function canPush(): bool
    {
        return $this->serverPush !== null;
    }

    /**
     * Push a resource to the client
     * 
     * @param string $path
     * @param mixed $content
     * @param array<string, string> $headers
     * @param string $method
     * @return bool
     */
    public function push(string $path, $content, array $headers = [], string $method = 'GET'): bool
    {
        if (!$this->canPush()) {
            return false;
        }

        return $this->serverPush->push($path, $content, $headers, $method);
    }

    /**
     * Push CSS file
     * 
     * @param string $path
     * @param string $content
     * @param array<string, string> $headers
     * @return bool
     */
    public function pushCss(string $path, string $content, array $headers = []): bool
    {
        if (!$this->canPush()) {
            return false;
        }

        return $this->serverPush->pushCss($path, $content, $headers);
    }

    /**
     * Push JavaScript file
     * 
     * @param string $path
     * @param string $content
     * @param array<string, string> $headers
     * @return bool
     */
    public function pushJs(string $path, string $content, array $headers = []): bool
    {
        if (!$this->canPush()) {
            return false;
        }

        return $this->serverPush->pushJs($path, $content, $headers);
    }

    /**
     * Push JSON data
     * 
     * @param string $path
     * @param array|object $data
     * @param array<string, string> $headers
     * @return bool
     */
    public function pushJson(string $path, $data, array $headers = []): bool
    {
        if (!$this->canPush()) {
            return false;
        }

        return $this->serverPush->pushJson($path, $data, $headers);
    }

    /**
     * Push image
     * 
     * @param string $path
     * @param string $content
     * @param array<string, string> $headers
     * @return bool
     */
    public function pushImage(string $path, string $content, array $headers = []): bool
    {
        if (!$this->canPush()) {
            return false;
        }

        return $this->serverPush->pushImage($path, $content, $headers);
    }

    /**
     * Push HTML page
     * 
     * @param string $path
     * @param string $content
     * @param array<string, string> $headers
     * @return bool
     */
    public function pushHtml(string $path, string $content, array $headers = []): bool
    {
        if (!$this->canPush()) {
            return false;
        }

        return $this->serverPush->pushHtml($path, $content, $headers);
    }

    /**
     * Push font file
     * 
     * @param string $path
     * @param string $content
     * @param array<string, string> $headers
     * @return bool
     */
    public function pushFont(string $path, string $content, array $headers = []): bool
    {
        if (!$this->canPush()) {
            return false;
        }

        return $this->serverPush->pushFont($path, $content, $headers);
    }

    /**
     * Push multiple resources
     * 
     * @param array<array{path: string, content: mixed, headers?: array<string, string>, method?: string}> $resources
     * @return int Number of successful pushes
     */
    public function pushMultiple(array $resources): int
    {
        if (!$this->canPush()) {
            return 0;
        }

        return $this->serverPush->pushMultiple($resources);
    }

    /**
     * Push critical resources for a web page
     * 
     * @param array<string, string> $criticalResources
     * @return int Number of successful pushes
     */
    public function pushCriticalResources(array $criticalResources): int
    {
        if (!$this->canPush()) {
            return 0;
        }

        return $this->serverPush->pushCriticalResources($criticalResources);
    }

    /**
     * Check if this is an HTTP/2 request
     * 
     * @return bool
     */
    public function isHttp2(): bool
    {
        return $this->protocol === 'HTTP/2.0';
    }
}
