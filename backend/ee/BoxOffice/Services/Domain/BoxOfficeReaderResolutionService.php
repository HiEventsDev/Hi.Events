<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerStripePlatformDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\DTO\TerminalReaderDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\StripeTerminalReaderService;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Validation\ValidationException;

class BoxOfficeReaderResolutionService
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly StripeTerminalReaderService $readerService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function resolve(EventDomainObject $event, ?int $readerId): ?TerminalReaderDTO
    {
        if ($readerId === null) {
            return null;
        }

        $organizer = $this->organizerRepository
            ->loadRelation(OrganizerStripePlatformDomainObject::class)
            ->findById($event->getOrganizerId());

        $reader = $this->readerService->listForOrganizer($organizer)->readers
            ->first(fn (TerminalReaderDTO $reader) => $reader->id === $readerId && $reader->is_available);

        if ($reader === null) {
            throw ValidationException::withMessages([
                'stripe_terminal_reader_id' => __('That card reader is not available. Pick another one or continue without a reader.'),
            ]);
        }

        return $reader;
    }
}
