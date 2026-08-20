<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Webhook\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class WebhookTargetDTO extends BaseDataObject
{
    public function __construct(
        public string $host,
        public int $port,
        /** @var array<int, string> */
        public array $ipAddresses,
    ) {}

    /**
     * @return array<int, string> a single curl CURLOPT_RESOLVE entry pinning the host to every address validated here.
     */
    public function toCurlResolveEntries(): array
    {
        $addresses = array_map(
            fn (string $ipAddress) => str_contains($ipAddress, ':') ? '['.$ipAddress.']' : $ipAddress,
            $this->ipAddresses,
        );

        return [sprintf('%s:%d:%s', $this->host, $this->port, implode(',', $addresses))];
    }
}
