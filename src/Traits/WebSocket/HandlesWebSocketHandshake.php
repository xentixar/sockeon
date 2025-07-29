<?php
/**
 * HandlesWebSocketHandshake trait
 * 
 * Manages WebSocket handshake process and authentication
 * 
 * @package     Sockeon\Sockeon\Traits\WebSocket
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Traits\WebSocket;

use Sockeon\Sockeon\Core\Config;

trait HandlesWebSocketHandshake
{
    /**
     * Perform WebSocket handshake with client
     * 
     * @param int $clientId The client identifier
     * @param resource $client The client socket resource
     * @param string $data The HTTP handshake request
     * @return bool Whether the handshake was successful
     */
    protected function performHandshake(int $clientId, $client, string $data): bool
    {
        $origin = null;
        if (preg_match('/Origin:\s(.+)\r\n/i', $data, $originMatches)) {
            $origin = trim($originMatches[1]);
        }

        if ($origin !== null && !$this->isOriginAllowed($origin)) {
            $response = "HTTP/1.1 403 Forbidden\r\nContent-Type: text/plain\r\n\r\nOrigin not allowed";
            fwrite($client, $response);
            return false;
        }

        $requestUri = null;
        if (preg_match('/GET\s+(.*?)\s+HTTP/i', $data, $uriMatches)) {
            $requestUri = trim($uriMatches[1]);
        }

        $authKey = Config::getAuthKey();
        if ($authKey !== null) {
            $queryString = '';
            if ($requestUri !== null && strpos($requestUri, '?') !== false) {
                $queryString = substr($requestUri, strpos($requestUri, '?') + 1);
            }
            
            parse_str($queryString, $queryParams);
            
            if (!isset($queryParams['key']) || $queryParams['key'] !== $authKey) {
                $response = "HTTP/1.1 401 Unauthorized\r\nContent-Type: text/plain\r\n\r\nInvalid authentication key";
                fwrite($client, $response);
                $this->server->getLogger()->debug("[WebSocket Authentication] Authentication failed for client: $clientId");
                return false;
            }
            
            $this->server->getLogger()->debug("[WebSocket Authentication] Authentication successful for client: $clientId");
        }

        if (preg_match('/Sec-WebSocket-Key:\s(.+)\r\n/i', $data, $matches)) {
            $secKey = trim($matches[1]);
            $acceptKey = base64_encode(sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

            $headers = [
                "HTTP/1.1 101 Switching Protocols",
                "Upgrade: websocket",
                "Connection: Upgrade",
                "Sec-WebSocket-Accept: $acceptKey",
                "Sec-WebSocket-Version: 13",
            ];

            if ($origin !== null && $this->isOriginAllowed($origin)) {
                $headers[] = "Access-Control-Allow-Origin: $origin";
            }

            $response = implode("\r\n", $headers) . "\r\n\r\n";
            fwrite($client, $response);
            $this->handshakes[$clientId] = true;
            
            $this->server->getRouter()->dispatchSpecialEvent($clientId, 'connect');
            
            return true;
        }

        return false;
    }

    /**
     * Check if the origin is allowed
     *
     * @param string $origin
     * @return bool
     */
    protected function isOriginAllowed(string $origin): bool
    {
        if (in_array('*', $this->allowedOrigins)) {
            return true;
        }

        return in_array($origin, $this->allowedOrigins);
    }
}
