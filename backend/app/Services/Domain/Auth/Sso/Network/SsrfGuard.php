<?php

namespace HiEvents\Services\Domain\Auth\Sso\Network;

use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\Exceptions\SsoException;
use HiEvents\Services\Infrastructure\Network\PublicIpAddress;

class SsrfGuard
{
    public function __construct(
        private readonly HostResolver $resolver,
    ) {}

    /**
     * @throws SsoException
     */
    public function pin(string $url): PinnedTarget
    {
        $parts = parse_url($url);
        $host = isset($parts['host']) ? strtolower(trim($parts['host'], '[]')) : null;

        if (($parts['scheme'] ?? null) !== 'https' || $host === null || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw $this->blocked('Only https URLs on public hosts are allowed');
        }

        $addresses = $this->resolver->resolve($host);

        if ($addresses === []) {
            throw new SsoException(SsoErrorCode::PROVIDER_UNAVAILABLE, 'The identity provider host could not be resolved');
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                throw $this->blocked('The identity provider resolves to a non-public address');
            }
        }

        return new PinnedTarget($host, (int) ($parts['port'] ?? 443), $addresses[0]);
    }

    public function isPublicAddress(string $address): bool
    {
        return PublicIpAddress::isPublic($address);
    }

    private function blocked(string $reason): SsoException
    {
        return new SsoException(SsoErrorCode::ISSUER_NOT_ALLOWED, $reason);
    }
}
