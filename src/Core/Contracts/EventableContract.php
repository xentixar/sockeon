<?php

namespace Sockeon\Sockeon\Core\Contracts;

interface EventableContract
{
    public function broadcastAs(): string;

    /**
     * @return array<mixed>
     */
    public function broadcastWith(): array;

    public function broadcastRoom(): ?string;

    public function broadcastNamespace(): ?string;
}
