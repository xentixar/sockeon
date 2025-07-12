<?php

namespace Sockeon\Sockeon\Traits\Server;

trait HandlesHttpWs
{
    protected function handleHttpWs(
        int $clientId,
        mixed $client,
        string $data
    ): void {
        if (!isset($this->clientTypes[$clientId])) {
            $this->clientTypes[$clientId] = 'unknown';
        }

        if ($this->clientTypes[$clientId] === 'unknown') {
            if (
                str_starts_with($data, 'GET ') || str_starts_with($data, 'POST ') ||
                str_starts_with($data, 'PUT ') || str_starts_with($data, 'DELETE ') ||
                str_starts_with($data, 'OPTIONS ') || str_starts_with($data, 'PATCH ') ||
                str_starts_with($data, 'HEAD ')
            ) {
                if (str_contains($data, 'Upgrade: websocket')) {
                    $this->clientTypes[$clientId] = 'ws';
                    $this->logger->debug("[Sockeon Identification] WebSocket client identified: $clientId");
                } else {
                    $this->clientTypes[$clientId] = 'http';
                    $this->logger->debug("[Sockeon Identification] HTTP client identified: $clientId");
                }
            }
        }

        switch ($this->clientTypes[$clientId]) {
            case 'ws':
                if (is_resource($client)) {
                    $keepAlive = $this->wsHandler->handle($clientId, $client, $data);
                    if (!$keepAlive) {
                        $this->disconnectClient($clientId);
                    }
                }
                break;

            case 'http':
                if (is_resource($client)) {
                    $this->httpHandler->handle($clientId, $client, $data);
                }
                $this->disconnectClient($clientId);
                break;

            default:
                $this->logger->warning("[Sockeon Identification] Unknown protocol, disconnecting client: $clientId");
                $this->disconnectClient($clientId);
        }
    }
}
