<?php
/**
 * HandlesHttpRequests trait
 * 
 * Manages HTTP request parsing and processing
 * 
 * @package     Sockeon\Sockeon\Traits\Http
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Traits\Http;

use Sockeon\Sockeon\Http\Request;
use Sockeon\Sockeon\Http\Response;

trait HandlesHttpRequests
{
    /**
     * Parse raw HTTP request into structured format
     * 
     * @param string $data The raw HTTP request data
     * @return array<string, mixed> The parsed request as an associative array
     */
    protected function parseHttpRequest(string $data): array
    {
        $lines = explode("\r\n", $data);
        $firstLine = $lines[0];
        $requestLine = $firstLine ? explode(' ', $firstLine) : [];

        $method = $requestLine[0] ?? '';
        $path = $requestLine[1] ?? '/';
        $protocol = $requestLine[2] ?? '';

        $headers = [];
        $headersDone = false;
        $body = '';
        
        foreach ($lines as $line) {
            if (!$headersDone) {
                if (empty(trim($line))) {
                    $headersDone = true;
                    continue;
                }
                
                if (str_contains($line, ':')) {
                    list($key, $value) = explode(':', $line, 2);
                    $headers[trim($key)] = trim($value);
                }
            } else {
                $body .= $line;
            }
        }
        
        $query = [];
        $url = parse_url($path);
        
        if (isset($url['path'])) {
            $path = $url['path'];
            
            if (empty($path)) {
                $path = '/';
            } elseif ($path[0] !== '/') {
                $path = '/' . $path;
            }
        } else {
            $path = '/';
        }
        
        if (isset($url['query'])) {
            parse_str($url['query'], $query);
        }

        if (empty($body)) {
            $body = [];
        } else {
            $parsedBody = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $body = $parsedBody;
            }
        }
        
        return [
            'method' => $method,
            'path' => $path,
            'protocol' => $protocol,
            'headers' => $headers,
            'query' => $query,
            'body' => $body
        ];
    }

    /**
     * Process an HTTP request and generate response
     * 
     * @param Request $request The Request object
     * @return string The HTTP response string
     */
    protected function processRequest(Request $request): string
    {
        $path = $request->getPath();
        $method = $request->getMethod();
        
        $result = $this->server->getRouter()->dispatchHttp($request);
        
        if ($result instanceof Response) {
            $response = $result;
        } elseif ($result !== null) {
            if (is_array($result) || is_object($result)) {
                $response = Response::json($result);
            } else {
                $response = new Response($result);
            }
        } else {
            $response = Response::notFound();
        }
        
        return $response->toString();
    }
}
