<?php

namespace HiEvents\Services\Infrastructure\Stripe;

use HiEvents\DomainObjects\Enums\StripePlatform;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use Stripe\StripeClient;

class StripeClientFactory
{
    public function __construct(
        private readonly StripeConfigurationService $configurationService
    ) {
    }

    /**
     * @throws StripeClientConfigurationException
     */
    public function createForPlatform(?StripePlatform $platform = null): StripeClient
    {
        $secretKey = $this->configurationService->getSecretKey($platform);

        if (empty($secretKey)) {
            $platformName = $platform?->value ?: 'default';
            throw new StripeClientConfigurationException(
                __('Stripe secret key not configured for platform: :platform', ['platform' => $platformName])
            );
        }

        return new StripeClient($secretKey);
    }
}
