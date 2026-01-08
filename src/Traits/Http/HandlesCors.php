<?php

/**
 * HandlesCors trait
 *
 * Manages CORS (Cross-Origin Resource Sharing) functionality
 *
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Traits\Http;

use Sockeon\Sockeon\Http\Request;
use Sockeon\Sockeon\Http\Response;

trait HandlesCors
{
    /**
     * Handle CORS preflight request
     *
     * @param Request $request
     * @return string
     */
    protected function handleCorsPreflightRequest(Request $request): string
    {
        $response = new Response('', 204);
        $response->setHeader('Content-Type', 'text/plain');
        return $this->applyCorsHeaders($request, $response->toString(), true);
    }

    /**
     * Apply CORS headers to a response
     *
     * @param Request $request The request object
     * @param string|Response $response The response or response string
     * @param bool $isPreflight Whether this is a preflight request
     * @return string The response with CORS headers
     */
    protected function applyCorsHeaders(Request $request, $response, bool $isPreflight = false): string
    {
        if ($response instanceof Response) {
            $response = $response->toString();
        }

        $origin = $request->getHeader('Origin');

        if (!$origin || !is_string($origin)) {
            return $response;
        }

        if (!$this->corsConfig->isOriginAllowed($origin)) {
            return $response;
        }

        [$headers, $body] = $this->parseHttpResponse($response);

        $headers['Access-Control-Allow-Origin'] = $origin;

        if ($this->corsConfig->isCredentialsAllowed()) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        if ($isPreflight) {
            $allowedMethods = $this->corsConfig->getAllowedMethods();
            $headers['Access-Control-Allow-Methods'] = implode(', ', $allowedMethods);

            $allowedHeaders = $this->corsConfig->getAllowedHeaders();
            $headers['Access-Control-Allow-Headers'] = implode(', ', $allowedHeaders);

            $headers['Access-Control-Max-Age'] = (string) $this->corsConfig->getMaxAge();
        }

        $headerString = '';
        if (isset($headers['Status-Line'])) {
            $headerString .= $headers['Status-Line'] . "\r\n";
            unset($headers['Status-Line']);
        }

        foreach ($headers as $name => $value) {
            $headerString .= $name . ": " . $value . "\r\n";
        }

        return $headerString . "\r\n" . $body;
    }

    /**
     * Parse HTTP response into headers and body
     *
     * @param string $response The HTTP response string
     * @return array{0: array<string, string>, 1: string} [headers, body]
     */
    protected function parseHttpResponse(string $response): array
    {
        $parts = explode("\r\n\r\n", $response, 2);

        $headerLines = explode("\r\n", $parts[0]);
        $statusLine = array_shift($headerLines);

        $headers = ['Status-Line' => $statusLine];
        foreach ($headerLines as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }

        $body = $parts[1] ?? '';

        return [$headers, $body];
    }
}
