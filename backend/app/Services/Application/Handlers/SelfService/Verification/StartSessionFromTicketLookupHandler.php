<?php

namespace HiEvents\Services\Application\Handlers\SelfService\Verification;

use HiEvents\Exceptions\InvalidTicketLookupTokenException;
use HiEvents\Services\Domain\SelfService\DTO\SelfServiceSessionDTO;
use HiEvents\Services\Domain\SelfService\SelfServiceVerificationService;
use HiEvents\Services\Domain\TicketLookup\TicketLookupTokenService;

class StartSessionFromTicketLookupHandler
{
    public function __construct(
        private readonly TicketLookupTokenService $ticketLookupTokenService,
        private readonly SelfServiceVerificationService $verificationService,
    ) {}

    /**
     * @throws InvalidTicketLookupTokenException
     */
    public function handle(string $lookupToken): SelfServiceSessionDTO
    {
        return $this->verificationService->startSession($this->ticketLookupTokenService->emailFor($lookupToken));
    }
}
