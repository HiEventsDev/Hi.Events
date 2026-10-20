<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Organizer\Payment\Stripe\Terminal;

use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\DTO\TerminalReadersResponseDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\StripeTerminalReaderService;
use HiEvents\Exceptions\ResourceNotFoundException;

class GetStripeTerminalReadersHandler
{
    public function __construct(
        private readonly OrganizerTerminalLookupService $organizerLookup,
        private readonly StripeTerminalReaderService $readerService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $organizerId, int $accountId): TerminalReadersResponseDTO
    {
        return $this->readerService->listForOrganizer($this->organizerLookup->findOrFail($organizerId, $accountId));
    }
}
