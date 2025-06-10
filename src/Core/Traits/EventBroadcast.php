<?php

namespace Sockeon\Sockeon\Core\Traits;

use Sockeon\Sockeon\Client\Client;

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
            $client = new Client();
            $client->connect();
            $client->broadcast($eventName, $data, $namespace, $room);
            $client->disconnect();
        } catch (\Throwable $e) {

        }
    }
}