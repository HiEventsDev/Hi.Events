<?php

namespace HiEvents\Services\Domain\Attendee;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Helper\EmailMasker;
use HiEvents\Mail\Attendee\AttendeeDetailsChangedMail;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Illuminate\Support\Facades\Mail;

class AttendeeDetailsChangeNotificationService
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly SendAttendeeTicketService $sendAttendeeTicketService,
    ) {}

    public function notify(
        int $attendeeId,
        int $eventId,
        ?string $previousEmail,
        bool $emailChanged,
        array $oldValues,
        array $newValues,
    ): void {
        if (! $emailChanged && $previousEmail === null) {
            return;
        }

        $event = $this->eventRepository
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->loadRelation(EventSettingDomainObject::class)
            ->findById($eventId);

        $attendee = $this->attendeeRepository
            ->loadRelation(new Relationship(OrderDomainObject::class, nested: [
                new Relationship(OrderItemDomainObject::class),
            ], name: 'order'))
            ->loadRelation(new Relationship(
                domainObject: EventOccurrenceDomainObject::class,
                name: 'event_occurrence',
            ))
            ->loadRelation(new Relationship(
                domainObject: ProductDomainObject::class,
                name: 'product',
            ))
            ->findById($attendeeId);

        if ($emailChanged) {
            $this->sendAttendeeTicketService->send(
                order: $attendee->getOrder(),
                attendee: $attendee,
                event: $event,
                eventSettings: $event->getEventSettings(),
                organizer: $event->getOrganizer(),
            );
        }

        if ($previousEmail === null) {
            return;
        }

        Mail::to($previousEmail)->queue(new AttendeeDetailsChangedMail(
            ticketTitle: $attendee->getProduct()?->getTitle() ?? __('Ticket'),
            event: $event,
            organizer: $event->getOrganizer(),
            eventSettings: $event->getEventSettings(),
            changedFields: $emailChanged
                ? $this->formatEmailChange($oldValues, $newValues)
                : $this->formatChangedFields($oldValues, $newValues),
        ));
    }

    private function formatEmailChange(array $oldValues, array $newValues): array
    {
        return [
            __('Email') => [
                'old' => $oldValues['email'] ?? '',
                'new' => ($newValues['email'] ?? null) === null ? '' : EmailMasker::mask($newValues['email']),
            ],
        ];
    }

    private function formatChangedFields(array $oldValues, array $newValues): array
    {
        $fieldLabels = [
            'first_name' => __('First Name'),
            'last_name' => __('Last Name'),
            'email' => __('Email'),
        ];

        $changedFields = [];
        foreach ($fieldLabels as $field => $label) {
            if (! array_key_exists($field, $newValues)) {
                continue;
            }

            $changedFields[$label] = [
                'old' => $oldValues[$field] ?? '',
                'new' => $newValues[$field],
            ];
        }

        return $changedFields;
    }
}
