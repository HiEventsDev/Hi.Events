<?php

namespace HiEvents\Services\Domain\Mail;

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
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Mail\Order\OrderFailed;
use HiEvents\Mail\Organizer\OrderSummaryForOrganizer;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Attendee\SendAttendeeTicketService;
use HiEvents\Services\Domain\Email\MailBuilderService;
use Illuminate\Mail\Mailer;

class SendOrderDetailsService
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Mailer $mailer,
        private readonly SendAttendeeTicketService $sendAttendeeTicketService,
        private readonly MailBuilderService $mailBuilderService,
    ) {}

    public function sendOrderSummaryAndTicketEmails(OrderDomainObject $order): void
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
                    new Relationship(
                        domainObject: ProductDomainObject::class,
                        name: 'product',
                    ),
                ],
            ))
            ->loadRelation(InvoiceDomainObject::class)
            ->findById($order->getId());

        $event = $this->eventRepository
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ]))
            ->loadRelation(new Relationship(EventOccurrenceDomainObject::class, nested: [
                new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                    new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
                ]),
            ]))
            ->findById($order->getEventId());

        if ($order->isOrderCompleted() || $order->isOrderAwaitingOfflinePayment()) {
            $this->sendOrderSummaryEmails($order, $event);
            $this->sendAttendeeTicketEmails($order, $event);
        }

        if ($order->isOrderFailed()) {
            $this->mailer
                ->to($order->getEmail())
                ->locale($order->getLocale())
                ->send(new OrderFailed(
                    order: $order,
                    event: $event,
                    organizer: $event->getOrganizer(),
                    eventSettings: $event->getEventSettings(),
                ));
        }
    }

    public function sendCustomerOrderSummary(
        OrderDomainObject $order,
        EventDomainObject $event,
        OrganizerDomainObject $organizer,
        EventSettingDomainObject $eventSettings,
        ?InvoiceDomainObject $invoice = null,
        ?EventOccurrenceDomainObject $occurrence = null,
    ): void {
        $mail = $this->mailBuilderService->buildOrderSummaryMail(
            $order,
            $event,
            $eventSettings,
            $organizer,
            $invoice,
            $occurrence ?? $this->resolvePrimaryOccurrence($order),
        );

        $this->mailer
            ->to($order->getEmail())
            ->locale($order->getLocale())
            ->send($mail);
    }

    private function resolvePrimaryOccurrence(OrderDomainObject $order): ?EventOccurrenceDomainObject
    {
        $items = $order->getOrderItems();
        if ($items === null || $items->isEmpty()) {
            return null;
        }

        $distinct = $items
            ->map(fn (OrderItemDomainObject $item) => $item->getEventOccurrence())
            ->filter()
            ->unique(fn (EventOccurrenceDomainObject $occ) => $occ->getId());

        return $distinct->count() === 1 ? $distinct->first() : null;
    }

    private function sendAttendeeTicketEmails(OrderDomainObject $order, EventDomainObject $event): void
    {
        $sentEmails = [];
        foreach ($order->getAttendees() as $attendee) {
            if (in_array($attendee->getEmail(), $sentEmails, true)) {
                continue;
            }

            $this->sendAttendeeTicketService->send(
                order: $order,
                attendee: $attendee,
                event: $event,
                eventSettings: $event->getEventSettings(),
                organizer: $event->getOrganizer(),
            );

            $sentEmails[] = $attendee->getEmail();
        }
    }

    private function sendOrderSummaryEmails(OrderDomainObject $order, EventDomainObject $event): void
    {
        $this->sendCustomerOrderSummary(
            order: $order,
            event: $event,
            organizer: $event->getOrganizer(),
            eventSettings: $event->getEventSettings(),
            invoice: $order->getLatestInvoice(),
        );

        if ($order->getIsManuallyCreated() || ! $event->getEventSettings()->getNotifyOrganizerOfNewOrders()) {
            return;
        }

        $this->mailer
            ->to($event->getOrganizer()->getEmail())
            ->send(new OrderSummaryForOrganizer($order, $event));
    }
}
