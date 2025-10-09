<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\DTO\AccountApplicationFeeDTO;
use HiEvents\DomainObjects\Enums\StripePlatform;
use Illuminate\Support\Collection;

class AccountDomainObject extends Generated\AccountDomainObjectAbstract
{
    private ?AccountConfigurationDomainObject $configuration = null;

    /** @var Collection<int, AccountStripePlatformDomainObject>|null */
    private ?Collection $stripePlatforms = null;

    public function getApplicationFee(): AccountApplicationFeeDTO
    {
        /** @var AccountConfigurationDomainObject $applicationFee */
        $applicationFee = $this->getConfiguration();

        return new AccountApplicationFeeDTO(
            $applicationFee->getPercentageApplicationFee(),
            $applicationFee->getFixedApplicationFee()
        );
    }

    public function getConfiguration(): ?AccountConfigurationDomainObject
    {
        return $this->configuration;
    }

    public function setConfiguration(AccountConfigurationDomainObject $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function getAccountStripePlatforms(): ?Collection
    {
        return $this->stripePlatforms;
    }

    public function setAccountStripePlatforms(Collection $stripePlatforms): void
    {
        $this->stripePlatforms = $stripePlatforms;
    }

    /**
     * Get the primary active Stripe platform for this account
     * Returns the platform with setup completed, preferring the most recent
     */
    public function getPrimaryStripePlatform(): ?AccountStripePlatformDomainObject
    {
        if (!$this->stripePlatforms || $this->stripePlatforms->isEmpty()) {
            return null;
        }

        return $this->stripePlatforms
            ->filter(fn($platform) => $platform->getStripeSetupCompletedAt() !== null)
            ->sortByDesc(fn($platform) => $platform->getCreatedAt())
            ->first();
    }

    /**
     * Get the Stripe platform for a specific platform type
     * Handles null platform for open-source installations
     */
    public function getStripePlatformByType(?StripePlatform $platformType): ?AccountStripePlatformDomainObject
    {
        if (!$this->stripePlatforms || $this->stripePlatforms->isEmpty()) {
            return null;
        }

        return $this->stripePlatforms
            ->filter(fn($platform) => $platform->getStripeConnectPlatform() === $platformType?->value)
            ->first();
    }

    public function getActiveStripeAccountId(): ?string
    {
        return $this->getPrimaryStripePlatform()?->getStripeAccountId();
    }

    public function getActiveStripePlatform(): ?StripePlatform
    {
        $primaryPlatform = $this->getPrimaryStripePlatform();
        if (!$primaryPlatform || !$primaryPlatform->getStripeConnectPlatform()) {
            return null;
        }

        return StripePlatform::fromString($primaryPlatform->getStripeConnectPlatform());
    }

    /**
     * Check if Stripe is set up and ready for payments
     */
    public function isStripeSetupComplete(): bool
    {
        return $this->getPrimaryStripePlatform() !== null;
    }
}
