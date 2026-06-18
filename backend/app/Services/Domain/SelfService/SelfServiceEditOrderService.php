<?php

namespace HiEvents\Services\Domain\SelfService;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Helper\IdHelper;
use HiEvents\Mail\Order\OrderDetailsChangedMail;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Mail\MailBrandingService;
use HiEvents\Services\Domain\Mail\SendOrderDetailsService;
use HiEvents\Services\Domain\SelfService\DTO\EditOrderResultDTO;
use Illuminate\Support\Facades\Mail;

class SelfServiceEditOrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrderAuditLogService $orderAuditLogService,
        private readonly SendOrderDetailsService $sendOrderDetailsService,
        private readonly MailBrandingService $mailBrandingService,
    ) {}

    public function editOrder(
        OrderDomainObject $order,
        ?string $firstName,
        ?string $lastName,
        ?string $email,
        string $ipAddress,
        ?string $userAgent
    ): EditOrderResultDTO {
        $oldValues = [];
        $newValues = [];
        $emailChanged = false;
        $shortIdChanged = false;
        $newShortId = null;

        $updateData = [];

        if ($firstName !== null && $firstName !== $order->getFirstName()) {
            $oldValues['first_name'] = $order->getFirstName();
            $newValues['first_name'] = $firstName;
            $updateData['first_name'] = $firstName;
        }

        if ($lastName !== null && $lastName !== $order->getLastName()) {
            $oldValues['last_name'] = $order->getLastName();
            $newValues['last_name'] = $lastName;
            $updateData['last_name'] = $lastName;
        }

        if ($email !== null && $email !== $order->getEmail()) {
            $oldValues['email'] = $order->getEmail();
            $newValues['email'] = $email;
            $updateData['email'] = $email;
            $emailChanged = true;
        }

        if (! empty($updateData)) {
            $oldEmail = $order->getEmail();

            if ($emailChanged) {
                $newShortId = IdHelper::shortId(IdHelper::ORDER_PREFIX);
                $updateData['short_id'] = $newShortId;
                $shortIdChanged = true;

                $oldValues['short_id'] = $order->getShortId();
                $newValues['short_id'] = $newShortId;
            }

            $this->orderRepository->updateWhere(
                attributes: $updateData,
                where: ['id' => $order->getId()]
            );

            $event = $this->loadEventWithRelations($order->getEventId());

            if ($emailChanged) {
                $this->sendConfirmationToNewEmail($order->getId(), $event);
            }

            $this->sendChangeNotificationToOldEmail(
                oldEmail: $oldEmail,
                event: $event,
                oldValues: $oldValues,
                newValues: $newValues
            );

            $this->orderAuditLogService->logOrderUpdate(
                order: $order,
                oldValues: $oldValues,
                newValues: $newValues,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );
        }

        return new EditOrderResultDTO(
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
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->loadRelation(new Relationship(domainObject: EventOccurrenceDomainObject::class, nested: [
                new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                    new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
                ]),
            ]))
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ]))
            ->findById($eventId);
    }

    private function sendConfirmationToNewEmail(int $orderId, EventDomainObject $event): void
    {
        $order = $this->orderRepository
            ->loadRelation(new Relationship(
                domainObject: OrderItemDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: EventOccurrenceDomainObject::class,
                        nested: [
                            new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
                            ]),
                        ],
                        name: 'event_occurrence',
                    ),
                ],
            ))
            ->loadRelation(new Relationship(
                domainObject: AttendeeDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: EventOccurrenceDomainObject::class,
                        nested: [
                            new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
                            ]),
                        ],
                        name: 'event_occurrence',
                    ),
                ],
            ))
            ->loadRelation(InvoiceDomainObject::class)
            ->findById($orderId);

        $this->sendOrderDetailsService->sendCustomerOrderSummary(
            order: $order,
            event: $event,
            organizer: $event->getOrganizer(),
            eventSettings: $event->getEventSettings(),
            invoice: $order->getLatestInvoice()
        );
    }

    private function sendChangeNotificationToOldEmail(
        string $oldEmail,
        EventDomainObject $event,
        array $oldValues,
        array $newValues
    ): void {
        $changedFields = $this->formatChangedFields($oldValues, $newValues);

        $mail = new OrderDetailsChangedMail(
            event: $event,
            organizer: $event->getOrganizer(),
            eventSettings: $event->getEventSettings(),
            changedFields: $changedFields
        );

        Mail::to($oldEmail)->queue($mail->withBranding($this->mailBrandingService->resolveForEvent($event->getId())));
    }

    private function formatChangedFields(array $oldValues, array $newValues): array
    {
        $fieldLabels = [
            'first_name' => __('First Name'),
            'last_name' => __('Last Name'),
            'email' => __('Email'),
            'short_id' => __('Order Reference'),
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
