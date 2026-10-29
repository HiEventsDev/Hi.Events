<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Webhook;

use HiEvents\Exceptions\UnsafeWebhookUrlException;
use HiEvents\Services\Infrastructure\Webhook\DTO\WebhookTargetDTO;
use Illuminate\Support\Facades\Config;

class WebhookUrlValidator
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    private const BLOCKED_HOSTS = [
        'localhost',
        '127.0.0.1',
        '::1',
        '0.0.0.0',
    ];

    private const BLOCKED_TLDS = [
        '.localhost',
        '.local',
        '.internal',
        '.intranet',
    ];

    private const CLOUD_METADATA_HOSTS = [
        '169.254.169.254',
        'metadata.google.internal',
        'metadata.goog',
    ];

    /**
     * @throws UnsafeWebhookUrlException
     */
    public function validate(string $url): WebhookTargetDTO
    {
        $parsedUrl = parse_url($url);

        if ($parsedUrl === false || ! isset($parsedUrl['host']) || $parsedUrl['host'] === '') {
            throw new UnsafeWebhookUrlException(__('The :attribute must be a valid URL.'));
        }

        $scheme = strtolower($parsedUrl['scheme'] ?? '');

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new UnsafeWebhookUrlException(__('The :attribute must use http or https protocol.'));
        }

        $host = $this->unwrapIpv6Literal(strtolower($parsedUrl['host']));
        $port = $parsedUrl['port'] ?? self::DEFAULT_PORTS[$scheme];

        if ($this->isWhitelistedHost($host)) {
            return new WebhookTargetDTO(
                host: $host,
                port: $port,
                ipAddresses: $this->resolveHostAddresses($host),
            );
        }

        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            throw new UnsafeWebhookUrlException(__('The :attribute cannot point to localhost or internal addresses.'));
        }

        if ($this->hasBlockedTld($host)) {
            throw new UnsafeWebhookUrlException(__('The :attribute cannot use reserved domain names.'));
        }

        if ($this->isCloudMetadataHost($host)) {
            throw new UnsafeWebhookUrlException(__('The :attribute cannot point to cloud metadata endpoints.'));
        }

        $ipAddresses = $this->resolveHostAddresses($host);

        foreach ($ipAddresses as $ipAddress) {
            if (! $this->isPubliclyRoutable($ipAddress)) {
                throw new UnsafeWebhookUrlException(
                    __('The :attribute cannot point to private or internal IP addresses.')
                );
            }
        }

        return new WebhookTargetDTO(
            host: $host,
            port: $port,
            ipAddresses: $ipAddresses,
        );
    }

    /**
     * @return array<int, string>
     *
     * @throws UnsafeWebhookUrlException
     */
    private function resolveHostAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if ($address !== null && filter_var($address, FILTER_VALIDATE_IP)) {
                $addresses[] = $address;
            }
        }

        if ($addresses === []) {
            throw new UnsafeWebhookUrlException(__('The :attribute could not be resolved to a public address.'));
        }

        return array_values(array_unique($addresses));
    }

    private function isPubliclyRoutable(string $ipAddress): bool
    {
        $normalised = $this->normaliseToRoutableIp($ipAddress);

        if (! filter_var($normalised, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (filter_var($normalised, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        if (str_starts_with($normalised, '169.254.')) {
            return false;
        }

        $binary = @inet_pton($normalised);

        if ($binary !== false && strlen($binary) === 16) {
            $firstByte = ord($binary[0]);

            if (($firstByte & 0xFE) === 0xFC) {
                return false;
            }

            if ($firstByte === 0xFE && (ord($binary[1]) & 0xC0) === 0x80) {
                return false;
            }
        }

        return true;
    }

    private function normaliseToRoutableIp(string $ipAddress): string
    {
        $binary = @inet_pton($ipAddress);

        if ($binary === false || strlen($binary) !== 16) {
            return $ipAddress;
        }

        $embeddedIpv4 = $this->extractEmbeddedIpv4($binary);

        return $embeddedIpv4 ?? $ipAddress;
    }

    private function extractEmbeddedIpv4(string $binary): ?string
    {
        $ipv4MappedPrefix = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff";
        $nat64Prefix = "\x00\x64\xff\x9b\x00\x00\x00\x00\x00\x00\x00\x00";
        $teredoPrefix = "\x20\x01\x00\x00";
        $sixToFourPrefix = "\x20\x02";

        if (str_starts_with($binary, $ipv4MappedPrefix) || str_starts_with($binary, $nat64Prefix)) {
            return inet_ntop(substr($binary, 12));
        }

        if (str_starts_with($binary, $sixToFourPrefix)) {
            return inet_ntop(substr($binary, 2, 4));
        }

        if (str_starts_with($binary, $teredoPrefix)) {
            return inet_ntop(substr($binary, 12) ^ "\xff\xff\xff\xff");
        }

        if (str_starts_with($binary, str_repeat("\x00", 12)) && substr($binary, 12) !== "\x00\x00\x00\x00") {
            return inet_ntop(substr($binary, 12));
        }

        return null;
    }

    private function unwrapIpv6Literal(string $host): string
    {
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            return substr($host, 1, -1);
        }

        return $host;
    }

    private function hasBlockedTld(string $host): bool
    {
        foreach (self::BLOCKED_TLDS as $tld) {
            if (str_ends_with($host, $tld)) {
                return true;
            }
        }

        return false;
    }

    private function isCloudMetadataHost(string $host): bool
    {
        foreach (self::CLOUD_METADATA_HOSTS as $metadataHost) {
            if ($host === $metadataHost || str_ends_with($host, '.'.$metadataHost)) {
                return true;
            }
        }

        return false;
    }

    private function isWhitelistedHost(string $host): bool
    {
        $whitelistedHosts = Config::string('app.allowed_internal_webhook_hosts');

        if (empty($whitelistedHosts)) {
            return false;
        }

        $allowedList = array_filter(array_map('trim', explode(',', $whitelistedHosts)));

        if (in_array($host, $allowedList, true)) {
            return true;
        }

        $resolved = gethostbyname($host);

        return $resolved !== $host && in_array($resolved, $allowedList, true);
    }
}
