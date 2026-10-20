<?php

namespace HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerStripePlatformRepositoryInterface;
use Psr\Log\LoggerInterface;

class DisconnectStripeConnectAccountHandler
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly OrganizerStripePlatformRepositoryInterface $organizerStripePlatformRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $organizerId, int $accountId, string $stripeAccountId): void
    {
        $organizer = $this->organizerRepository->findFirstWhere([
            'id' => $organizerId,
            'account_id' => $accountId,
        ]);

        if ($organizer === null) {
            throw new ResourceNotFoundException(__('Organizer not found.'));
        }

        $deletedCount = $this->organizerStripePlatformRepository->deleteWhere([
            'organizer_id' => $organizerId,
            'stripe_account_id' => $stripeAccountId,
        ]);

        if ($deletedCount === 0) {
            throw new ResourceNotFoundException(__('Stripe connection not found.'));
        }

        $this->logger->info('Stripe connect account disconnected from organizer', [
            'organizer_id' => $organizerId,
            'account_id' => $accountId,
            'stripe_account_id' => $stripeAccountId,
        ]);
    }
}
