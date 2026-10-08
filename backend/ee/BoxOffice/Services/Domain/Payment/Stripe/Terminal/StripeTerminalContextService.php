<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\DTO\TerminalContextDTO;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use HiEvents\Services\Infrastructure\Stripe\StripeConfigurationService;
use Illuminate\Config\Repository;

class StripeTerminalContextService
{
    public function __construct(
        private readonly StripeClientFactory $stripeClientFactory,
        private readonly StripeConfigurationService $stripeConfigurationService,
        private readonly Repository $config,
    ) {}

    public function isSaasMode(): bool
    {
        return (bool) $this->config->get('app.saas_mode_enabled');
    }

    /**
     * @throws StripeClientConfigurationException
     * @throws ResourceConflictException
     */
    public function forOrganizer(OrganizerDomainObject $organizer): TerminalContextDTO
    {
        if (! $this->isSaasMode()) {
            $platform = $this->stripeConfigurationService->getPrimaryPlatform();

            return new TerminalContextDTO(
                client: $this->stripeClientFactory->createForPlatform($platform),
                platform: $platform,
                stripe_account_id: null,
                organizer_stripe_platform_id: null,
            );
        }

        $primary = $organizer->getPrimaryStripePlatform();

        if ($primary === null) {
            throw new ResourceConflictException(__('Connect a Stripe account before adding card readers'));
        }

        $platform = $organizer->getActiveStripePlatform();

        return new TerminalContextDTO(
            client: $this->stripeClientFactory->createForPlatform($platform),
            platform: $platform,
            stripe_account_id: $primary->getStripeAccountId(),
            organizer_stripe_platform_id: $primary->getId(),
        );
    }
}
