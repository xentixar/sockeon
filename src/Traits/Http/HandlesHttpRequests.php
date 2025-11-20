<?php
/**
 * HandlesHttpRequests trait
 * 
 * Manages HTTP request parsing and processing
 * 
 * @package     Sockeon\Sockeon
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
        
        $bodyStartPos = strpos($data, "\r\n\r\n");
        if ($bodyStartPos !== false) {
            $headerSection = substr($data, 0, $bodyStartPos);
            $body = substr($data, $bodyStartPos + 4);
            
            $headerLines = explode("\r\n", $headerSection);
            for ($i = 1; $i < count($headerLines); $i++) {
                $line = $headerLines[$i];
                if (str_contains($line, ':')) {
                    list($key, $value) = explode(':', $line, 2);
                    $headers[trim($key)] = trim($value);
                }
            }
        } else {
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
        
        // Check for health check endpoint
        if ($this->server->getHealthCheckPath() !== null && $path === $this->server->getHealthCheckPath()) {
            return $this->handleHealthCheck($request);
        }
        
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

    /**
     * Handle health check endpoint
     * 
     * @param Request $request The Request object
     * @return string The HTTP response string
     */
    protected function handleHealthCheck(Request $request): string
    {
        // Only allow GET and HEAD methods for health check
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return Response::methodNotAllowed()->toString();
        }

        $uptime = $this->server->getUptime();
        $uptimeString = $this->server->getUptimeString();

        $healthData = [
            'status' => 'healthy',
            'timestamp' => time(),
            'server' => [
                'clients' => $this->server->getClientCount(),
                'uptime' => $uptime,
                'uptime_human' => $uptimeString,
            ]
        ];

        $response = Response::json($healthData, 200);
        return $response->toString();
    }
}
