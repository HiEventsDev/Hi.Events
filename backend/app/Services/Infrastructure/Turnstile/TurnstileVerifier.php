<?php

namespace HiEvents\Services\Infrastructure\Turnstile;

use Illuminate\Config\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpClient;
use Psr\Log\LoggerInterface;

class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly Repository $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function isEnabled(): bool
    {
        return ! empty($this->config->get('services.turnstile.secret_key'));
    }

    public function verify(?string $token, ?string $ipAddress): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        if ($token === null || $token === '') {
            return false;
        }

        try {
            $response = $this->httpClient
                ->asForm()
                ->timeout(5)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => $this->config->get('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ipAddress,
                ]));
        } catch (ConnectionException $exception) {
            $this->logger->warning('Turnstile verification could not reach Cloudflare', [
                'exception' => $exception->getMessage(),
            ]);

            return true;
        }

        return $response->successful() && $response->json('success') === true;
    }
}
