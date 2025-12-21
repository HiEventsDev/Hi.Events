<?php

namespace HiEvents\Services\Infrastructure\Stripe;

use HiEvents\DomainObjects\Enums\StripePlatform;

class StripeConfigurationService
{
    public function getSecretKey(?StripePlatform $platform = null): ?string
    {
        return match ($platform) {
            StripePlatform::CANADA => config('services.stripe.ca_secret_key', config('services.stripe.secret_key')),
            StripePlatform::IRELAND => config('services.stripe.ie_secret_key', config('services.stripe.secret_key')),
            default => config('services.stripe.secret_key'),
        };
    }

    public function getPublicKey(?StripePlatform $platform = null): ?string
    {
        return match ($platform) {
            StripePlatform::CANADA => config('services.stripe.ca_public_key', config('services.stripe.public_key')),
            StripePlatform::IRELAND => config('services.stripe.ie_public_key', config('services.stripe.public_key')),
            default => config('services.stripe.public_key'),
        };
    }

    public function getPrimaryPlatform(): ?StripePlatform
    {
        $platformString = config('services.stripe.primary_platform');
        return StripePlatform::fromString($platformString);
    }

    public function getAllWebhookSecrets(): array
    {
        $secrets =  array_filter([
            'default' => config('services.stripe.webhook_secret'),
            StripePlatform::CANADA->value => config('services.stripe.ca_webhook_secret'),
            StripePlatform::IRELAND->value => config('services.stripe.ie_webhook_secret'),
        ]);

        // order by primary platform first
        $primary = $this->getPrimaryPlatform()?->value;

        if ($primary && isset($secrets[$primary])) {
            $primarySecret = [$primary => $secrets[$primary]];
            unset($secrets[$primary]);
            return $primarySecret + $secrets;
        }

        return $secrets;
    }
}
