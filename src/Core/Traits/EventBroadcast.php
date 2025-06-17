<?php

namespace Sockeon\Sockeon\Core\Traits;

use Sockeon\Sockeon\Client\Client;
use Sockeon\Sockeon\Core\Server;

trait EventBroadcast
{
     /**
     * Statically broadcast an event to multiple clients
     *
     * @param string|self|string $event The event name, Event instance, or Event class string
     * @param array<string, mixed> $data The data to send
     * @param string|null $namespace Optional namespace to broadcast within
     * @param string|null $room Optional room to broadcast to
     * @return void
     */
    public static function broadcast($event, array $data, string $namespace = '/', string $room = ''): void
    {
        $eventName = self::resolveEventName($event);

        try {
            $server = Server::getInstance();
            if ($server !== null) {
                $server->broadcast($eventName, $data, $namespace, $room);
            } else {
                $client = new Client();
                $client->connect();
                $client->broadcast($eventName, $data, $namespace, $room);
                $client->disconnect();
            }
        } catch (\Throwable $e) {
            // Silently handle errors (add logging if needed)
        }
    }
}