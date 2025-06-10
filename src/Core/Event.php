<?php
/**
 * Abstract Event class
 * 
 * Base class for all WebSocket events in Sockeon
 * Provides standard methods for event identification and properties
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Core;

use Sockeon\Sockeon\Core\Traits\SocketEvent;

abstract class Event
{
    /**
     * Server instance for static event operations
     * @var Server|null
     */
    protected static ?Server $serverInstance = null;
    
    /**
     * The event name (used for event identification)
     * @var string
     */
    protected string $name;
    
    /**
     * The human-readable event label
     * @var string
     */
    protected string $label;

    /**
     * Constructor
     *
     * @param string|null $name The event name (optional, will use name() method if not provided)
     * @param string|null $label The human-readable label for the event (optional)
     */
    public function __construct(?string $name = null, ?string $label = null)
    {
        $this->name = $name ?? $this->name();
        $this->label = $label ?? $this->name;
    }

    /**
     * Define the event name
     * 
     * @return string The name of the event
     */
    abstract public function name(): string;

    /**
     * Define the human-readable label for the event
     * 
     * @return string The label for the event
     */
    abstract public function label(): string;
    
    /**
     * Get the event name
     * 
     * @return string The event name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the human-readable event label
     * 
     * @return string The event label
     */
    public function getLabel(): string
    {
        return $this->label;
    }
    
    /**
     * Convert the event to a string
     * 
     * @return string The event name
     */
    public function __toString(): string
    {
        return $this->getName();
    }
    
    /**
     * Resolves an event identifier to its name
     * 
     * @param string|self|string $event The event name, Event instance, or Event class string
     * @return string The resolved event name
     */
    public static function resolveEventName($event): string
    {
        if (is_string($event) && class_exists($event) && is_subclass_of($event, self::class)) {
            $eventClass = new $event();
            return $eventClass->getName();
        } elseif ($event instanceof self) {
            return $event->getName();
        }
        
        return $event;
    }
    
    /**
     * Sets the server instance for static event operations
     * 
     * @param Server $server The server instance
     * @return void
     */
    public static function setServerInstance(Server $server): void
    {
        self::$serverInstance = $server;
    }
    
    /**
     * Gets the server instance
     * 
     * @return Server|null The server instance
     * @throws \RuntimeException If server instance is not set
     */
    public static function getServerInstance(): ?Server
    {
        if (self::$serverInstance === null) {
            throw new \RuntimeException("Server instance not set in Event class");
        }
        return self::$serverInstance;
    }
    
    /**
     * Statically emit an event to a specific client
     * 
     * @param int $clientId   The ID of the client to send to
     * @param string|self|string $event The event name, Event instance, or Event class string
     * @param array<string, mixed> $data       The data to send
     * @return void
     * @throws \RuntimeException If server instance is not set
     */
    public static function emit(int $clientId, $event, array $data): void
    {
        $server = self::getServerInstance();
        $eventName = self::resolveEventName($event);
        $server->send($clientId, $eventName, $data); //@phpstan-ignore-line
    }

    /**
     * Statically broadcast an event to multiple clients
     *
     * @param string|self|string $event The event name, Event instance, or Event class string
     * @param array<string, mixed> $data The data to send
     * @param string|null $namespace Optional namespace to broadcast within
     * @param string|null $room Optional room to broadcast to
     * @return void
     * @throws \RuntimeException If server instance is not set
     */
    public static function broadcast($event, array $data, ?string $namespace = null, ?string $room = null): void
    {
        $server = self::getServerInstance();
        $eventName = self::resolveEventName($event);
        $server->broadcast($eventName, $data, $namespace, $room); //@phpstan-ignore-line
    }
}
