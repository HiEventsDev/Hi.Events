<?php

namespace HiEvents\Services\Application\Handlers\TicketResale;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SelfServiceVerificationRequiredException;
use HiEvents\Services\Application\Handlers\TicketResale\DTO\TicketResaleLinkDTO;
use HiEvents\Services\Domain\SelfService\VerifiedTicketAccessService;
use HiEvents\Services\Domain\TicketResale\DTO\TicketResaleStateDTO;
use HiEvents\Services\Domain\TicketResale\TicketResaleContextService;
use HiEvents\Services\Domain\TicketResale\TicketResaleListingService;
use HiEvents\Services\Domain\TicketResale\TicketResaleStateService;
use Throwable;

class ListTicketForResaleHandler
{
    public function __construct(
        private readonly TicketResaleContextService $contextService,
        private readonly VerifiedTicketAccessService $verifiedTicketAccessService,
        private readonly TicketResaleListingService $listingService,
        private readonly TicketResaleStateService $stateService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     * @throws SelfServiceVerificationRequiredException
     * @throws Throwable
     */
    public function handle(TicketResaleLinkDTO $link): TicketResaleStateDTO
    {
        $context = $this->contextService->forOrderTicketLinkWithRecordedFees($link->event_id, $link->order_short_id, $link->attendee_short_id);

        $this->verifiedTicketAccessService->assertControlsOrder($link->session_token, $context->order);

        $this->listingService->list($context, $link->ip_address, $link->user_agent);

        return $this->stateService->forOrderLinkTicket($context);
    }
}
