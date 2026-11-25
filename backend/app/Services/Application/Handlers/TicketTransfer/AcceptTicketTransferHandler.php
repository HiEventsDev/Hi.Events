<?php

namespace HiEvents\Services\Application\Handlers\TicketTransfer;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Services\Application\Handlers\TicketTransfer\DTO\AcceptTicketTransferDTO;
use HiEvents\Services\Domain\TicketTransfer\TicketTransferClaimService;
use Throwable;

class AcceptTicketTransferHandler
{
    public function __construct(
        private readonly TicketTransferClaimService $claimService,
    ) {}

    /**
     * @throws ResourceConflictException
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function handle(AcceptTicketTransferDTO $acceptance): AttendeeDomainObject
    {
        return $this->claimService->accept(
            token: $acceptance->token,
            firstName: $acceptance->first_name,
            lastName: $acceptance->last_name,
            questions: $acceptance->questions,
            ipAddress: $acceptance->ip_address,
            userAgent: $acceptance->user_agent,
        );
    }
}
