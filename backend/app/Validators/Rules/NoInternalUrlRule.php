<?php

namespace HiEvents\Validators\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoInternalUrlRule implements ValidationRule
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    private const BLOCKED_HOSTS = [
        'localhost',
        '127.0.0.1',
        '::1',
        '0.0.0.0',
    ];

    private const BLOCKED_TLDS = [
        '.localhost',
    ];

    private const CLOUD_METADATA_HOSTS = [
        '169.254.169.254',
        'metadata.google.internal',
        'metadata.goog',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail(__('The :attribute must be a valid URL.'));
            return;
        }

        $parsedUrl = parse_url($value);
        if ($parsedUrl === false || !isset($parsedUrl['host'])) {
            $fail(__('The :attribute must be a valid URL.'));
            return;
        }

        $scheme = strtolower($parsedUrl['scheme'] ?? '');
        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            $fail(__('The :attribute must use http or https protocol.'));
            return;
        }

        $host = strtolower($parsedUrl['host']);

        // Handle IPv6 addresses wrapped in brackets
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        if ($this->isBlockedHost($host)) {
            $fail(__('The :attribute cannot point to localhost or internal addresses.'));
            return;
        }

        if ($this->isBlockedTld($host)) {
            $fail(__('The :attribute cannot use reserved domain names.'));
            return;
        }

        if ($this->isCloudMetadataHost($host)) {
            $fail(__('The :attribute cannot point to cloud metadata endpoints.'));
            return;
        }

        if ($this->isPrivateIpAddress($host)) {
            $fail(__('The :attribute cannot point to private or internal IP addresses.'));
            return;
        }
    }

    private function isBlockedHost(string $host): bool
    {
        return in_array($host, self::BLOCKED_HOSTS, true);
    }

    private function isBlockedTld(string $host): bool
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
            if ($host === $metadataHost || str_ends_with($host, '.' . $metadataHost)) {
                return true;
            }
        }
        return false;
    }

    private function isPrivateIpAddress(string $host): bool
    {
        $ip = gethostbyname($host);

        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        if (str_starts_with($ip, '169.254.')) {
            return true;
        }

        return false;
    }
}
