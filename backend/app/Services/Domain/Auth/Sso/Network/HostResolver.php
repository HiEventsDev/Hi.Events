<?php

namespace HiEvents\Services\Domain\Auth\Sso\Network;

class HostResolver
{
    /**
     * @return string[]
     */
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        $addresses = array_values(array_filter(array_map(
            static fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        )));

        return $addresses !== [] ? $addresses : (@gethostbynamel($host) ?: []);
    }

    /**
     * @return string[]
     */
    public function txtRecords(string $name): array
    {
        $records = @dns_get_record($name, DNS_TXT) ?: [];

        return array_values(array_filter(array_map(
            static fn (array $record) => isset($record['entries']) ? implode('', $record['entries']) : ($record['txt'] ?? null),
            $records,
        )));
    }
}
