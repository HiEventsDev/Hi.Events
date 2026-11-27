<?php

namespace HiEvents\Services\Application\Handlers\TicketTransfer;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SelfServiceVerificationRequiredException;
use HiEvents\Services\Application\Handlers\TicketTransfer\DTO\TicketTransferLinkDTO;
use HiEvents\Services\Domain\SelfService\VerifiedTicketAccessService;
use HiEvents\Services\Domain\TicketTransfer\TicketTransferContextService;
use HiEvents\Services\Domain\TicketTransfer\TicketTransferInitiationService;

class CancelTicketTransferHandler
{
    public function __construct(
        private readonly TicketTransferContextService $contextService,
        private readonly VerifiedTicketAccessService $verifiedTicketAccessService,
        private readonly TicketTransferInitiationService $initiationService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     * @throws SelfServiceVerificationRequiredException
     */
    public function handle(TicketTransferLinkDTO $link): void
    {
        $context = $this->contextService->forTicketLink($link->event_id, $link->attendee_short_id, $link->order_short_id);

        $this->verifiedTicketAccessService->assertControlsTicket($link->session_token, $context->attendee, $context->order);

        $this->initiationService->cancel($context, $link->ip_address, $link->user_agent);
    }
}
