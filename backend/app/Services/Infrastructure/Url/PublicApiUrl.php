<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Url;

use Illuminate\Contracts\Config\Repository;

class PublicApiUrl
{
    private const API_PATH_BEHIND_THE_FRONTEND = '/api';

    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    public function __construct(
        private readonly Repository $config,
    ) {}

    public function to(string $path): string
    {
        return $this->base().'/'.ltrim($path, '/');
    }

    private function base(): string
    {
        $frontendUrl = rtrim((string) $this->config->get('app.frontend_url'), '/');
        $apiUrl = $this->apiUrlFromEnvironment();

        return $apiUrl === null || $this->isFrontendRoot($apiUrl, $frontendUrl)
            ? $frontendUrl.self::API_PATH_BEHIND_THE_FRONTEND
            : $apiUrl;
    }

    private function apiUrlFromEnvironment(): ?string
    {
        $apiUrl = $this->config->get('app.api_url');
        $isSetInEnvironment = is_string($apiUrl)
            && trim($apiUrl) !== ''
            && $apiUrl === $this->config->get('app.url');

        return $isSetInEnvironment ? rtrim(trim($apiUrl), '/') : null;
    }

    private function isFrontendRoot(string $url, string $frontendUrl): bool
    {
        $address = parse_url($url);
        $frontend = parse_url($frontendUrl);

        return is_array($address)
            && is_array($frontend)
            && strtolower($address['host'] ?? '') === strtolower($frontend['host'] ?? '')
            && $this->explicitPort($address) === $this->explicitPort($frontend)
            && trim($address['path'] ?? '', '/') === '';
    }

    private function explicitPort(array $address): ?int
    {
        $port = $address['port'] ?? null;
        $defaultPort = self::DEFAULT_PORTS[strtolower($address['scheme'] ?? '')] ?? null;

        return $port === $defaultPort ? null : $port;
    }
}
