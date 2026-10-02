<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Organizer\Payment\Stripe\Terminal;

use HiEvents\DomainObjects\StripeTerminalReaderDomainObject;
use HiEvents\Enterprise\BoxOffice\Exceptions\Stripe\TerminalLocationAddressMissingException;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\StripeTerminalReaderService;
use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use Stripe\Exception\ApiErrorException;

class RegisterStripeTerminalReaderHandler
{
    public function __construct(
        private readonly OrganizerTerminalLookupService $organizerLookup,
        private readonly StripeTerminalReaderService $readerService,
        private readonly LicensedFeatureUsageService $featureUsage,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     * @throws StripeClientConfigurationException
     * @throws TerminalLocationAddressMissingException
     * @throws ApiErrorException
     */
    public function handle(int $organizerId, int $accountId, string $registrationCode, string $label): StripeTerminalReaderDomainObject
    {
        $reader = $this->readerService->register(
            organizer: $this->organizerLookup->findOrFail($organizerId, $accountId),
            registrationCode: $registrationCode,
            label: $label,
        );

        $this->featureUsage->forget();

        return $reader;
    }
}
