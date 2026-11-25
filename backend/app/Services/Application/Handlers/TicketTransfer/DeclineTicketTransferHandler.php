<?php

namespace HiEvents\Services\Application\Handlers\TicketTransfer;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Services\Domain\TicketTransfer\TicketTransferClaimService;
use Throwable;

class DeclineTicketTransferHandler
{
    public function __construct(
        private readonly TicketTransferClaimService $claimService,
    ) {}

    /**
     * @throws ResourceConflictException
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function handle(string $token, ?string $ipAddress, ?string $userAgent): void
    {
        $this->claimService->decline($token, $ipAddress, $userAgent);
    }
}
