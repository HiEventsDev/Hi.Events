<?php

namespace HiEvents\Services\Application\Handlers\TicketResale;

use HiEvents\DomainObjects\TicketResaleListingDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Services\Application\Handlers\TicketResale\DTO\TicketResaleListingActionDTO;
use HiEvents\Services\Domain\TicketResale\ResaleListingOverviewService;
use HiEvents\Services\Domain\TicketResale\ResaleRefundService;
use Throwable;

class RetryTicketResaleRefundHandler
{
    public function __construct(
        private readonly ResaleRefundService $refundService,
        private readonly ResaleListingOverviewService $overviewService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     * @throws Throwable
     */
    public function handle(TicketResaleListingActionDTO $action): TicketResaleListingDomainObject
    {
        $this->refundService->retryFailed(
            $this->overviewService->findForEvent($action->event_id, $action->listing_id)
        );

        return $this->overviewService->findForEvent($action->event_id, $action->listing_id);
    }
}
