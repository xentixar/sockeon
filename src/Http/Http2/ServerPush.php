<?php
/**
 * ServerPush class
 * 
 * Utility class for HTTP/2 server push functionality
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http\Http2;

use Sockeon\Sockeon\Http\Http2Handler;
use Sockeon\Sockeon\Http\Response;
use Sockeon\Sockeon\Contracts\Http2\ServerPushInterface;

class ServerPush implements ServerPushInterface
{
    /**
     * HTTP/2 handler instance
     * @var Http2Handler
     */
    protected Http2Handler $handler;

    /**
     * Client socket resource
     * @var resource
     */
    protected $client;

    /**
     * Parent stream ID
     * @var int
     */
    protected int $parentStreamId;

    /**
     * Constructor
     * 
     * @param Http2Handler $handler
     * @param resource $client
     * @param int $parentStreamId
     */
    public function __construct(Http2Handler $handler, $client, int $parentStreamId)
    {
        $this->handler = $handler;
        $this->client = $client;
        $this->parentStreamId = $parentStreamId;
    }

    /**
     * Push a resource to the client
     * 
     * @param string $path The resource path
     * @param mixed $content The resource content
     * @param array<string, string> $headers Additional headers
     * @param string $method HTTP method (default: GET)
     * @return bool True if push was successful
     */
    public function push(string $path, $content, array $headers = [], string $method = 'GET'): bool
    {
        try {
            // Default headers
            $defaultHeaders = [
                'content-type' => $this->guessContentType($path),
                'cache-control' => 'public, max-age=3600'
            ];

            $headers = array_merge($defaultHeaders, $headers);

            $this->handler->sendServerPush($this->client, $this->parentStreamId, $method, $path, $headers, $content);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Push a CSS file
     * 
     * @param string $path
     * @param string $content
     * @param array<string, string> $headers
     * @return bool
     */
    public function pushCss(string $path, string $content, array $headers = []): bool
    {
        $headers['content-type'] = 'text/css';
        return $this->push($path, $content, $headers);
    }

    /**
     * Push a JavaScript file
     * 
     * @param string $path
     * @param string $content
     * @param array<string, string> $headers
     * @return bool
     */
    public function pushJs(string $path, string $content, array $headers = []): bool
    {
        $headers['content-type'] = 'application/javascript';
        return $this->push($path, $content, $headers);
    }

    /**
     * Push an image
     * 
     * @param string $path
     * @param string $content
     * @param array<string, string> $headers
     * @return bool
     */
    public function pushImage(string $path, string $content, array $headers = []): bool
    {
        $headers['content-type'] = $this->guessImageContentType($path);
        return $this->push($path, $content, $headers);
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
        $headers['content-type'] = 'application/json';
        $content = json_encode($data);
        return $this->push($path, $content, $headers);
    }

    /**
     * Push an HTML page
     * 
     * @param string $path
     * @param string $content
     * @param array<string, string> $headers
     * @return bool
     */
    public function pushHtml(string $path, string $content, array $headers = []): bool
    {
        $headers['content-type'] = 'text/html; charset=utf-8';
        return $this->push($path, $content, $headers);
    }

    /**
     * Push a font file
     * 
     * @param string $path
     * @param string $content
     * @param array<string, string> $headers
     * @return bool
     */
    public function pushFont(string $path, string $content, array $headers = []): bool
    {
        $headers['content-type'] = $this->guessFontContentType($path);
        $headers['cache-control'] = 'public, max-age=31536000'; // Cache fonts for 1 year
        return $this->push($path, $content, $headers);
    }

    /**
     * Push multiple resources at once
     * 
     * @param array<array{path: string, content: mixed, headers?: array<string, string>, method?: string}> $resources
     * @return int Number of successful pushes
     */
    public function pushMultiple(array $resources): int
    {
        $successful = 0;

        foreach ($resources as $resource) {
            $path = $resource['path'];
            $content = $resource['content'];
            $headers = $resource['headers'] ?? [];
            $method = $resource['method'] ?? 'GET';

            if ($this->push($path, $content, $headers, $method)) {
                $successful++;
            }
        }

        return $successful;
    }

    /**
     * Push critical resources for a web page
     * 
     * @param array<string, string> $criticalResources
     * @return int Number of successful pushes
     */
    public function pushCriticalResources(array $criticalResources): int
    {
        $resources = [];

        foreach ($criticalResources as $path => $content) {
            $resources[] = [
                'path' => $path,
                'content' => $content,
                'headers' => [
                    'cache-control' => 'public, max-age=86400', // Cache for 1 day
                    'content-type' => $this->guessContentType($path)
                ]
            ];
        }

        return $this->pushMultiple($resources);
    }

    /**
     * Guess content type based on file extension
     * 
     * @param string $path
     * @return string
     */
    protected function guessContentType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'html', 'htm' => 'text/html; charset=utf-8',
            'css' => 'text/css',
            'js', 'mjs' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'txt' => 'text/plain',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'eot' => 'application/vnd.ms-fontobject',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            default => 'application/octet-stream'
        };
    }

    /**
     * Guess image content type
     * 
     * @param string $path
     * @return string
     */
    protected function guessImageContentType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'ico' => 'image/x-icon',
            'tiff', 'tif' => 'image/tiff',
            default => 'image/jpeg'
        };
    }

    /**
     * Guess font content type
     * 
     * @param string $path
     * @return string
     */
    protected function guessFontContentType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'eot' => 'application/vnd.ms-fontobject',
            default => 'font/woff2'
        };
    }

    /**
     * Get parent stream ID
     * 
     * @return int
     */
    public function getParentStreamId(): int
    {
        return $this->parentStreamId;
    }
}
