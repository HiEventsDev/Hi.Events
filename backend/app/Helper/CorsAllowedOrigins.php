<?php

namespace HiEvents\Helper;

final class CorsAllowedOrigins
{
    public const WILDCARD = '*';

    /**
     * @return string[]
     */
    public static function resolve(?string $configured, ?string $frontendUrl): array
    {
        $origins = array_values(array_filter(array_map('trim', explode(',', (string) $configured))));

        if ($origins !== [] && ! in_array(self::WILDCARD, $origins, true)) {
            return $origins;
        }

        $frontendOrigins = self::originsOf((string) $frontendUrl);

        return $frontendOrigins === [] ? [self::WILDCARD] : $frontendOrigins;
    }

    /**
     * @return string[]
     */
    private static function originsOf(string $url): array
    {
        $url = trim($url);

        if ($url === '') {
            return [];
        }

        $parts = parse_url(str_contains($url, '://') ? $url : 'https://'.$url);

        if (! isset($parts['host'])) {
            return [];
        }

        $schemes = str_contains($url, '://') && $parts['scheme'] === 'https' ? ['https'] : ['https', 'http'];
        $hosts = [$parts['host'], self::wwwCounterpart($parts['host'])];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        $origins = [];
        foreach ($schemes as $scheme) {
            foreach (array_filter($hosts) as $host) {
                $origins[] = $scheme.'://'.$host.$port;
            }
        }

        return $origins;
    }

    private static function wwwCounterpart(string $host): ?string
    {
        if (str_starts_with($host, 'www.')) {
            return substr($host, 4);
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false || ! str_contains($host, '.')) {
            return null;
        }

        return 'www.'.$host;
    }
}
