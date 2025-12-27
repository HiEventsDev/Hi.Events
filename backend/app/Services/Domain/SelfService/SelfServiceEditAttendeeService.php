<?php

namespace HiEvents\Services\Domain\SelfService;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Helper\IdHelper;
use HiEvents\Mail\Attendee\AttendeeDetailsChangedMail;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Attendee\SendAttendeeTicketService;
use HiEvents\Services\Domain\SelfService\DTO\EditAttendeeResultDTO;
use Illuminate\Support\Facades\Mail;

class SelfServiceEditAttendeeService
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrderAuditLogService $orderAuditLogService,
        private readonly SendAttendeeTicketService $sendAttendeeTicketService,
    ) {}

    public function editAttendee(
        AttendeeDomainObject $attendee,
        ?string $firstName,
        ?string $lastName,
        ?string $email,
        string $ipAddress,
        ?string $userAgent
    ): EditAttendeeResultDTO {
        $oldValues = [];
        $newValues = [];
        $emailChanged = false;
        $shortIdChanged = false;
        $newShortId = null;

        $updateData = [];

        if ($firstName !== null && $firstName !== $attendee->getFirstName()) {
            $oldValues['first_name'] = $attendee->getFirstName();
            $newValues['first_name'] = $firstName;
            $updateData['first_name'] = $firstName;
        }

        if ($lastName !== null && $lastName !== $attendee->getLastName()) {
            $oldValues['last_name'] = $attendee->getLastName();
            $newValues['last_name'] = $lastName;
            $updateData['last_name'] = $lastName;
        }

        if ($email !== null && $email !== $attendee->getEmail()) {
            $oldValues['email'] = $attendee->getEmail();
            $newValues['email'] = $email;
            $updateData['email'] = $email;
            $emailChanged = true;
        }

        if (!empty($updateData)) {
            $oldEmail = $attendee->getEmail();

            if ($emailChanged) {
                $newShortId = IdHelper::shortId(IdHelper::ATTENDEE_PREFIX);
                $updateData['short_id'] = $newShortId;
                $shortIdChanged = true;

                $oldValues['short_id'] = $attendee->getShortId();
                $newValues['short_id'] = $newShortId;
            }

            $this->attendeeRepository->updateWhere(
                attributes: $updateData,
                where: ['id' => $attendee->getId()]
            );

            $event = $this->loadEventWithRelations($attendee->getEventId());

            if ($emailChanged) {
                $this->sendTicketToNewEmail($attendee->getId(), $event);
            }

            $this->sendChangeNotificationToOldEmail(
                oldEmail: $oldEmail,
                attendeeId: $attendee->getId(),
                event: $event,
                oldValues: $oldValues,
                newValues: $newValues
            );

            $this->orderAuditLogService->logAttendeeUpdate(
                attendee: $attendee,
                oldValues: $oldValues,
                newValues: $newValues,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );
        }

        return new EditAttendeeResultDTO(
            success: true,
            shortIdChanged: $shortIdChanged,
            newShortId: $newShortId,
            emailChanged: $emailChanged
        );
    }

    private function loadEventWithRelations(int $eventId): EventDomainObject
    {
        return $this->eventRepository
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->loadRelation(EventSettingDomainObject::class)
            ->findById($eventId);
    }

    private function sendTicketToNewEmail(int $attendeeId, EventDomainObject $event): void
    {
        $attendee = $this->attendeeRepository
            ->loadRelation(new Relationship(OrderDomainObject::class, nested: [
                new Relationship(OrderItemDomainObject::class),
            ], name: 'order'))
            ->findById($attendeeId);

        $this->sendAttendeeTicketService->send(
            order: $attendee->getOrder(),
            attendee: $attendee,
            event: $event,
            eventSettings: $event->getEventSettings(),
            organizer: $event->getOrganizer(),
        );
    }

    private function sendChangeNotificationToOldEmail(
        string $oldEmail,
        int $attendeeId,
        EventDomainObject $event,
        array $oldValues,
        array $newValues
    ): void {
        $attendee = $this->attendeeRepository
            ->loadRelation(new Relationship(ProductDomainObject::class, name: 'product'))
            ->findById($attendeeId);

        $changedFields = $this->formatChangedFields($oldValues, $newValues);

        Mail::to($oldEmail)->queue(new AttendeeDetailsChangedMail(
            ticketTitle: $attendee->getProduct()?->getTitle() ?? __('Ticket'),
            event: $event,
            organizer: $event->getOrganizer(),
            eventSettings: $event->getEventSettings(),
            changedFields: $changedFields
        ));
    }

    private function formatChangedFields(array $oldValues, array $newValues): array
    {
        $fieldLabels = [
            'first_name' => __('First Name'),
            'last_name' => __('Last Name'),
            'email' => __('Email'),
            'short_id' => __('Ticket Reference'),
        ];

        $changedFields = [];
        foreach ($oldValues as $field => $oldValue) {
            if ($field === 'short_id') {
                continue;
            }
            $label = $fieldLabels[$field] ?? $field;
            $changedFields[$label] = [
                'old' => $oldValue,
                'new' => $newValues[$field] ?? '',
            ];
        }

        return $changedFields;
    }
}
