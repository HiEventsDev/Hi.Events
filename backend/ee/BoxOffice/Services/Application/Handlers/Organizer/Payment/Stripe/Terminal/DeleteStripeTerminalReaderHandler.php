<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Organizer\Payment\Stripe\Terminal;

use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeReaderCache;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\StripeTerminalReaderService;
use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use HiEvents\Exceptions\ResourceNotFoundException;

class DeleteStripeTerminalReaderHandler
{
    public function __construct(
        private readonly OrganizerTerminalLookupService $organizerLookup,
        private readonly StripeTerminalReaderService $readerService,
        private readonly LicensedFeatureUsageService $featureUsage,
        private readonly BoxOfficeReaderCache $readerCache,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $organizerId, int $accountId, int $readerId): void
    {
        $this->readerService->delete($this->organizerLookup->findOrFail($organizerId, $accountId), $readerId);
        $this->featureUsage->forget();
        $this->readerCache->forget($organizerId);
    }
}
