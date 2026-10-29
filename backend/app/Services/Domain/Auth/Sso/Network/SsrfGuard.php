<?php

namespace HiEvents\Services\Domain\Auth\Sso\Network;

use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\Exceptions\SsoException;

class SsrfGuard
{
    private const BLOCKED_RANGES = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.88.99.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '::/128',
        '::1/128',
        '::ffff:0:0/96',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
        '100::/64',
        '2001::/32',
        '2001:db8::/32',
        '2002::/16',
        'fc00::/7',
        'fe80::/10',
        'ff00::/8',
    ];

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
        $packed = @inet_pton($address);

        if ($packed === false) {
            return false;
        }

        foreach (self::BLOCKED_RANGES as $range) {
            if ($this->inRange($packed, $range)) {
                return false;
            }
        }

        return true;
    }

    private function inRange(string $packedAddress, string $range): bool
    {
        [$network, $prefixLength] = explode('/', $range);
        $packedNetwork = inet_pton($network);

        if (strlen($packedNetwork) !== strlen($packedAddress)) {
            return false;
        }

        $prefixLength = (int) $prefixLength;
        $fullBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;

        if (strncmp($packedAddress, $packedNetwork, $fullBytes) !== 0) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($packedAddress[$fullBytes]) & $mask) === (ord($packedNetwork[$fullBytes]) & $mask);
    }

    private function blocked(string $reason): SsoException
    {
        return new SsoException(SsoErrorCode::ISSUER_NOT_ALLOWED, $reason);
    }
}
