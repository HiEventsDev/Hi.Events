<?php

declare(strict_types=1);

namespace HiEvents\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Prevents SSRF by blocking SMTP hosts that resolve to private/internal IP ranges.
 */
class NotInternalHost implements ValidationRule
{
    private const BLOCKED_HOSTNAMES = [
        'localhost',
        'metadata.google.internal',
        'metadata.google',
        '169.254.169.254',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $host = strtolower(trim($value));

        // Block known dangerous hostnames
        foreach (self::BLOCKED_HOSTNAMES as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.' . $blocked)) {
                $fail(__('This host is not allowed.'));
                return;
            }
        }

        // Block raw private IPs entered directly
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if ($this->isPrivateIp($host)) {
                $fail(__('This host resolves to a private or internal IP address and is not allowed.'));
                return;
            }
        }

        // Resolve the hostname to IP(s) and check each
        $ips = gethostbynamel($host);
        if ($ips === false) {
            // Can't resolve — allow it; SMTP will fail at send time if truly invalid.
            return;
        }

        foreach ($ips as $ip) {
            if ($this->isPrivateIp($ip)) {
                $fail(__('This host resolves to a private or internal IP address and is not allowed.'));
                return;
            }
        }
    }

    private function isPrivateIp(string $ip): bool
    {
        // PHP's built-in filter catches RFC1918, loopback, and reserved ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        // Additional: 169.254.x.x (link-local / AWS metadata)
        if (str_starts_with($ip, '169.254.')) {
            return true;
        }

        // Additional: 100.64.0.0/10 (CGNAT)
        $long = ip2long($ip);
        if ($long !== false) {
            $cgnatStart = ip2long('100.64.0.0');
            $cgnatEnd = ip2long('100.127.255.255');
            if ($long >= $cgnatStart && $long <= $cgnatEnd) {
                return true;
            }
        }

        return false;
    }
}
