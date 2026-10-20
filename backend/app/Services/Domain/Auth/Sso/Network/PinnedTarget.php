<?php

namespace HiEvents\Services\Domain\Auth\Sso\Network;

class PinnedTarget
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $address,
    ) {}

    public function curlResolveEntry(): string
    {
        $address = str_contains($this->address, ':') ? '['.$this->address.']' : $this->address;

        return $this->host.':'.$this->port.':'.$address;
    }
}
