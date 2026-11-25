<?php

namespace HiEvents\Services\Application\Handlers\TicketResale;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SelfServiceVerificationRequiredException;
use HiEvents\Services\Application\Handlers\TicketResale\DTO\TicketResaleLinkDTO;
use HiEvents\Services\Application\Handlers\TicketResale\DTO\TicketResaleQuoteDTO;
use HiEvents\Services\Domain\SelfService\VerifiedTicketAccessService;
use HiEvents\Services\Domain\TicketResale\TicketResaleContextService;
use HiEvents\Services\Domain\TicketResale\TicketResaleEligibilityService;
use HiEvents\Services\Domain\TicketResale\TicketResaleEstimateService;

class GetTicketResaleEstimateHandler
{
    public function __construct(
        private readonly TicketResaleContextService $contextService,
        private readonly VerifiedTicketAccessService $verifiedTicketAccessService,
        private readonly TicketResaleEligibilityService $eligibilityService,
        private readonly TicketResaleEstimateService $estimateService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws SelfServiceVerificationRequiredException
     */
    public function handle(TicketResaleLinkDTO $link): TicketResaleQuoteDTO
    {
        $context = $this->contextService->forOrderTicketLinkWithRecordedFees($link->event_id, $link->order_short_id, $link->attendee_short_id);

        $this->verifiedTicketAccessService->assertControlsOrder($link->session_token, $context->order);

        return new TicketResaleQuoteDTO(
            block: $this->eligibilityService->blockOnListing($context),
            estimate: $this->estimateService->estimate($context),
            cutoff: $this->eligibilityService->cutoff($context)->toIso8601String(),
        );
    }
}
