<?php
/**
 * ServerPushInterface
 * 
 * Interface for HTTP/2 server push functionality
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Contracts\Http2;

interface ServerPushInterface
{
    /**
     * Push a resource to the client
     * 
     * @param string $path The resource path
     * @param mixed $content The resource content
     * @param array<string, string> $headers Additional headers
     * @param string $method HTTP method
     * @return bool True if push was successful
     */
    public function push(string $path, $content, array $headers = [], string $method = 'GET'): bool;

    /**
     * Push multiple resources at once
     * 
     * @param array<array{path: string, content: mixed, headers?: array<string, string>, method?: string}> $resources
     * @return int Number of successful pushes
     */
    public function pushMultiple(array $resources): int;

    /**
     * Push CSS file
     * 
     * @param string $path
     * @param string $content
     * @param array<string, string> $headers
     * @return bool
     */
    public function pushCss(string $path, string $content, array $headers = []): bool;

    /**
     * Push JavaScript file
     * 
     * @param string $path
     * @param string $content
     * @param array<string, string> $headers
     * @return bool
     */
    public function pushJs(string $path, string $content, array $headers = []): bool;

    /**
     * Push JSON data
     * 
     * @param string $path
     * @param array|object $data
     * @param array<string, string> $headers
     * @return bool
     */
    public function pushJson(string $path, $data, array $headers = []): bool;
}
