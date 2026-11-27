<?php

namespace HiEvents\Services\Application\Handlers\TicketResale;

use HiEvents\DomainObjects\Enums\TicketResaleCloseReason;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\DomainObjects\TicketResaleListingDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use HiEvents\Services\Application\Handlers\TicketResale\DTO\TicketResaleListingActionDTO;
use HiEvents\Services\Domain\TicketResale\ResaleListingOverviewService;
use HiEvents\Services\Domain\TicketResale\TicketResaleClosureService;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Illuminate\Database\DatabaseManager;
use Throwable;

class CloseTicketResaleListingHandler
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly TransactionLockService $transactionLockService,
        private readonly TicketResaleListingRepositoryInterface $listingRepository,
        private readonly TicketResaleClosureService $closureService,
        private readonly ResaleListingOverviewService $overviewService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     * @throws Throwable
     */
    public function handle(TicketResaleListingActionDTO $action): TicketResaleListingDomainObject
    {
        $this->databaseManager->transaction(function () use ($action): void {
            $this->transactionLockService->lockEvent($action->event_id);

            $listing = $this->listingRepository->findByIdLocked($action->listing_id);

            if ($listing === null || $listing->getEventId() !== $action->event_id) {
                throw new ResourceNotFoundException(__('Resale listing not found'));
            }

            if ($listing->getStatus() !== TicketResaleListingStatus::LISTED->name) {
                throw new ResourceConflictException(__('This resale listing is already closed.'));
            }

            $this->closureService->closeUnheld($listing, TicketResaleCloseReason::CLOSED_BY_ORGANIZER);
        });

        return $this->overviewService->findForEvent($action->event_id, $action->listing_id);
    }
}
