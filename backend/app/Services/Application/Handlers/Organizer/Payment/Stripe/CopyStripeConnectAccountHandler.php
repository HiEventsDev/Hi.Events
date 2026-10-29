<?php

namespace HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe;

use HiEvents\DomainObjects\Generated\OrganizerStripePlatformDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerStripePlatformDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SaasModeEnabledException;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerStripePlatformRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO\CopyStripeConnectAccountDTO;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO\CreateStripeConnectAccountResponse;
use HiEvents\Services\Domain\Organizer\AssignCurrencyDefaultOrganizerConfigurationService;
use HiEvents\Services\Domain\Payment\Stripe\StripeAccountSyncService;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Throwable;

class CopyStripeConnectAccountHandler
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly OrganizerStripePlatformRepositoryInterface $organizerStripePlatformRepository,
        private readonly StripeAccountSyncService $stripeAccountSyncService,
        private readonly AssignCurrencyDefaultOrganizerConfigurationService $assignCurrencyDefaultOrganizerConfigurationService,
        private readonly DatabaseManager $databaseManager,
        private readonly Repository $config,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(CopyStripeConnectAccountDTO $command): CreateStripeConnectAccountResponse
    {
        if (! $this->config->get('app.saas_mode_enabled')) {
            throw new SaasModeEnabledException(
                __('Stripe Connect Account creation is only available in Saas Mode.'),
            );
        }

        return $this->databaseManager->transaction(fn () => $this->copy($command));
    }

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     */
    private function copy(CopyStripeConnectAccountDTO $command): CreateStripeConnectAccountResponse
    {
        $target = $this->organizerRepository
            ->loadRelation(OrganizerStripePlatformDomainObject::class)
            ->findFirstWhere([
                'id' => $command->targetOrganizerId,
                'account_id' => $command->accountId,
            ]);

        if ($target === null) {
            throw new ResourceNotFoundException(__('Organizer not found.'));
        }

        $source = $this->organizerRepository
            ->loadRelation(OrganizerStripePlatformDomainObject::class)
            ->findFirstWhere([
                'id' => $command->sourceOrganizerId,
                'account_id' => $command->accountId,
            ]);

        if ($source === null) {
            throw new ResourceNotFoundException(__('The source organizer was not found.'));
        }

        $sourcePlatform = $source->getPrimaryStripePlatform();
        if ($sourcePlatform === null) {
            throw new ResourceConflictException(
                __('The selected organizer does not have a connected Stripe account.'),
            );
        }

        $existing = $target->getOrganizerStripePlatforms()
            ?->first(fn (OrganizerStripePlatformDomainObject $row) => $row->getStripeAccountId() === $sourcePlatform->getStripeAccountId());

        if ($existing !== null) {
            $this->organizerStripePlatformRepository->updateWhere(
                attributes: [
                    OrganizerStripePlatformDomainObjectAbstract::STRIPE_CONNECT_ACCOUNT_TYPE => $sourcePlatform->getStripeConnectAccountType(),
                    OrganizerStripePlatformDomainObjectAbstract::STRIPE_CONNECT_PLATFORM => $sourcePlatform->getStripeConnectPlatform(),
                    OrganizerStripePlatformDomainObjectAbstract::STRIPE_SETUP_COMPLETED_AT => $sourcePlatform->getStripeSetupCompletedAt(),
                    OrganizerStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_DETAILS => $sourcePlatform->getStripeAccountDetails(),
                ],
                where: [
                    'id' => $existing->getId(),
                ],
            );
        } else {
            $this->organizerStripePlatformRepository->create([
                OrganizerStripePlatformDomainObjectAbstract::ORGANIZER_ID => $target->getId(),
                OrganizerStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_ID => $sourcePlatform->getStripeAccountId(),
                OrganizerStripePlatformDomainObjectAbstract::STRIPE_CONNECT_ACCOUNT_TYPE => $sourcePlatform->getStripeConnectAccountType(),
                OrganizerStripePlatformDomainObjectAbstract::STRIPE_CONNECT_PLATFORM => $sourcePlatform->getStripeConnectPlatform(),
                OrganizerStripePlatformDomainObjectAbstract::STRIPE_SETUP_COMPLETED_AT => $sourcePlatform->getStripeSetupCompletedAt(),
                OrganizerStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_DETAILS => $sourcePlatform->getStripeAccountDetails(),
            ]);
        }

        $sourceDetails = $sourcePlatform->getStripeAccountDetails();
        if (is_string($sourceDetails)) {
            $sourceDetails = json_decode($sourceDetails, true) ?: [];
        } elseif (! is_array($sourceDetails)) {
            $sourceDetails = [];
        }

        $this->stripeAccountSyncService->seedVatSettingForOrganizerIfMissing(
            organizerId: (int) $target->getId(),
            countryCode: $sourceDetails['country'] ?? null,
            stripeAccountId: $sourcePlatform->getStripeAccountId(),
        );

        $this->assignCurrencyDefaultOrganizerConfigurationService->assignForCountry(
            organizerId: (int) $target->getId(),
            countryCode: $sourceDetails['country'] ?? null,
        );

        return new CreateStripeConnectAccountResponse(
            stripeConnectAccountType: $sourcePlatform->getStripeConnectAccountType() ?? '',
            stripeAccountId: $sourcePlatform->getStripeAccountId() ?? '',
            organizer: $target,
            isConnectSetupComplete: $sourcePlatform->getStripeSetupCompletedAt() !== null,
        );
    }
}
