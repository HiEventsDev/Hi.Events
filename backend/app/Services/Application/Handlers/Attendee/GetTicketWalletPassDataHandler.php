<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Attendee;

use Carbon\CarbonImmutable;
use HiEvents\DataTransferObjects\Wallet\TicketWalletPassData;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Exceptions\WalletPassNotAvailableException;
use HiEvents\Helper\Url;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;

readonly class GetTicketWalletPassDataHandler
{
    public function __construct(
        private AttendeeRepositoryInterface $attendeeRepository,
        private EventRepositoryInterface $eventRepository,
    ) {}

    public function handle(int $eventId, string $attendeeShortId): TicketWalletPassData
    {
        $attendee = $this->attendeeRepository
            ->loadRelation(new Relationship(ProductDomainObject::class, name: 'product'))
            ->loadRelation(new Relationship(EventOccurrenceDomainObject::class, name: 'event_occurrence'))
            ->findFirstWhere([
                AttendeeDomainObjectAbstract::SHORT_ID => $attendeeShortId,
                AttendeeDomainObjectAbstract::EVENT_ID => $eventId,
            ]);

        if (! $attendee || $attendee->getStatus() !== AttendeeStatus::ACTIVE->name) {
            throw new WalletPassNotAvailableException(__('This ticket is not available for Wallet.'));
        }

        $event = $this->eventRepository
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->findFirstWhere([EventDomainObjectAbstract::ID => $eventId]);

        if (! $event) {
            throw new WalletPassNotAvailableException(__('This ticket is not available for Wallet.'));
        }

        $occurrence = $attendee->getEventOccurrence();
        $timezone = $event->getTimezone() ?: 'UTC';
        $startDate = $occurrence?->getStartDate() ?? $event->getStartDate();
        $endDate = $occurrence?->getEndDate() ?? $event->getEndDate();

        return new TicketWalletPassData(
            eventId: $eventId,
            serialNumber: $attendee->getPublicId(),
            eventTitle: $event->getTitle(),
            organizerName: $event->getOrganizer()?->getName() ?? config('app.name'),
            ticketTitle: $attendee->getProduct()?->getTitle() ?? __('Ticket'),
            attendeeName: $attendee->getFullName(),
            barcodeValue: $attendee->getPublicId(),
            startDate: $this->toIso8601($startDate, $timezone),
            endDate: $this->toIso8601($endDate, $timezone),
            timezone: $timezone,
            ticketUrl: sprintf(Url::getFrontEndUrlFromConfig(Url::ATTENDEE_TICKET), $eventId, $attendee->getShortId()),
        );
    }

    private function toIso8601(?string $date, string $timezone): ?string
    {
        return $date ? CarbonImmutable::parse($date, $timezone)->toIso8601String() : null;
    }
}
