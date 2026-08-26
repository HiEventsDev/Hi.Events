<?php

namespace HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe;

use HiEvents\DomainObjects\Enums\StripeConnectAccountType;
use HiEvents\DomainObjects\Enums\StripePlatform;
use HiEvents\DomainObjects\Generated\OrganizerStripePlatformDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerStripePlatformDomainObject;
use HiEvents\Exceptions\CreateStripeConnectAccountFailedException;
use HiEvents\Exceptions\CreateStripeConnectAccountLinksFailedException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SaasModeEnabledException;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerStripePlatformRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO\CreateStripeConnectAccountDTO;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO\CreateStripeConnectAccountResponse;
use HiEvents\Services\Domain\Payment\Stripe\StripeAccountSyncService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use HiEvents\Services\Infrastructure\Stripe\StripeConfigurationService;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Stripe\Account;
use Stripe\StripeClient;
use Throwable;

class CreateStripeConnectAccountHandler
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly OrganizerStripePlatformRepositoryInterface $organizerStripePlatformRepository,
        private readonly DatabaseManager $databaseManager,
        private readonly LoggerInterface $logger,
        private readonly Repository $config,
        private readonly StripeClientFactory $stripeClientFactory,
        private readonly StripeConfigurationService $stripeConfigurationService,
        private readonly StripeAccountSyncService $stripeAccountSyncService,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(CreateStripeConnectAccountDTO $command): CreateStripeConnectAccountResponse
    {
        if (! $this->config->get('app.saas_mode_enabled')) {
            throw new SaasModeEnabledException(
                __('Stripe Connect Account creation is only available in Saas Mode.'),
            );
        }

        return $this->databaseManager->transaction(fn () => $this->createOrGetStripeConnectAccount($command));
    }

    /**
     * @throws CreateStripeConnectAccountFailedException
     * @throws CreateStripeConnectAccountLinksFailedException
     * @throws ResourceNotFoundException
     * @throws StripeClientConfigurationException
     */
    private function createOrGetStripeConnectAccount(CreateStripeConnectAccountDTO $command): CreateStripeConnectAccountResponse
    {
        $organizer = $this->organizerRepository
            ->loadRelation(OrganizerStripePlatformDomainObject::class)
            ->findFirstWhere([
                'id' => $command->organizerId,
                'account_id' => $command->accountId,
            ]);

        if ($organizer === null) {
            throw new ResourceNotFoundException(__('Organizer not found.'));
        }

        if ($command->platform) {
            $platformToUse = StripePlatform::fromString($command->platform->value);
        } else {
            $platformToUse = $this->stripeConfigurationService->getPrimaryPlatform();
        }

        $organizerStripePlatform = $organizer->getStripePlatformByType($platformToUse);

        $stripeClient = $this->stripeClientFactory->createForPlatform($platformToUse);

        $stripeConnectAccount = $this->getOrCreateStripeConnectAccount(
            organizer: $organizer,
            organizerStripePlatform: $organizerStripePlatform,
            stripeClient: $stripeClient,
            platform: $platformToUse,
        );

        $response = new CreateStripeConnectAccountResponse(
            stripeConnectAccountType: $stripeConnectAccount->type,
            stripeAccountId: $stripeConnectAccount->id,
            organizer: $organizer,
            isConnectSetupComplete: $this->stripeAccountSyncService->isStripeAccountComplete($stripeConnectAccount),
        );

        if ($response->isConnectSetupComplete) {
            if ($organizerStripePlatform && $organizerStripePlatform->getStripeSetupCompletedAt() === null) {
                $this->stripeAccountSyncService->markAccountAsCompleteForOrganizer($organizerStripePlatform, $stripeConnectAccount);
            }

            return $response;
        }

        $connectUrl = $this->stripeAccountSyncService->createStripeAccountSetupUrl($stripeConnectAccount, $stripeClient, $organizer->getId());
        if ($connectUrl === null) {
            throw new CreateStripeConnectAccountLinksFailedException(
                message: __('There are issues with creating the Stripe Connect Account Link. Please try again.'),
            );
        }

        $response->connectUrl = $connectUrl;

        return $response;
    }

    /**
     * @throws CreateStripeConnectAccountFailedException
     */
    private function getOrCreateStripeConnectAccount(
        OrganizerDomainObject $organizer,
        ?OrganizerStripePlatformDomainObject $organizerStripePlatform,
        StripeClient $stripeClient,
        ?StripePlatform $platform
    ): Account {
        try {
            if ($organizerStripePlatform && $organizerStripePlatform->getStripeAccountId() !== null) {
                return $stripeClient->accounts->retrieve($organizerStripePlatform->getStripeAccountId());
            }

            $stripeAccount = $stripeClient->accounts->create([
                'type' => $this->config->get('app.stripe_connect_account_type')
                    ?? StripeConnectAccountType::EXPRESS->value,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Failed to create or fetch Stripe Connect Account: '.$e->getMessage(), [
                'organizerId' => $organizer->getId(),
                'stripeAccountId' => $organizerStripePlatform?->getStripeAccountId() ?? 'null',
                'platform' => $platform?->value ?? 'null',
                'exception' => $e,
            ]);

            throw new CreateStripeConnectAccountFailedException(
                message: __('There are issues with creating or fetching the Stripe Connect Account. Please try again.'),
                previous: $e,
            );
        }

        if (! $organizerStripePlatform) {
            $this->organizerStripePlatformRepository->create([
                OrganizerStripePlatformDomainObjectAbstract::ORGANIZER_ID => $organizer->getId(),
                OrganizerStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_ID => $stripeAccount->id,
                OrganizerStripePlatformDomainObjectAbstract::STRIPE_CONNECT_ACCOUNT_TYPE => $stripeAccount->type,
                OrganizerStripePlatformDomainObjectAbstract::STRIPE_CONNECT_PLATFORM => $platform?->value,
            ]);
        } else {
            $this->organizerStripePlatformRepository->updateWhere(
                attributes: [
                    OrganizerStripePlatformDomainObjectAbstract::STRIPE_ACCOUNT_ID => $stripeAccount->id,
                    OrganizerStripePlatformDomainObjectAbstract::STRIPE_CONNECT_ACCOUNT_TYPE => $stripeAccount->type,
                ],
                where: [
                    'id' => $organizerStripePlatform->getId(),
                ]
            );
        }

        return $stripeAccount;
    }
}
