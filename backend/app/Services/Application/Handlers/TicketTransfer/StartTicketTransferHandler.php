<?php

namespace HiEvents\Services\Application\Handlers\TicketTransfer;

use HiEvents\DomainObjects\TicketTransferDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SelfServiceVerificationRequiredException;
use HiEvents\Services\Application\Handlers\TicketTransfer\DTO\TicketTransferLinkDTO;
use HiEvents\Services\Domain\SelfService\VerifiedTicketAccessService;
use HiEvents\Services\Domain\TicketTransfer\TicketTransferContextService;
use HiEvents\Services\Domain\TicketTransfer\TicketTransferInitiationService;

class StartTicketTransferHandler
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
    public function handle(TicketTransferLinkDTO $link): TicketTransferDomainObject
    {
        $context = $this->contextService->forTicketLink($link->event_id, $link->attendee_short_id, $link->order_short_id);

        return $this->initiationService->start(
            context: $context,
            senderEmail: $this->verifiedTicketAccessService->assertControlsTicket($link->session_token, $context->attendee, $context->order),
            recipientEmail: (string) $link->recipient_email,
            ipAddress: $link->ip_address,
            userAgent: $link->user_agent,
        );
    }
}
