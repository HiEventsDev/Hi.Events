<?php

namespace TicketKitten\Service\Common\Mail;

use Illuminate\Mail\Mailer;
use TicketKitten\DomainObjects\AttendeeDomainObject;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\EventSettingDomainObject;
use TicketKitten\DomainObjects\OrderDomainObject;
use TicketKitten\DomainObjects\OrderItemDomainObject;
use TicketKitten\DomainObjects\OrganizerDomainObject;
use TicketKitten\Mail\AttendeeTicketMail;
use TicketKitten\Mail\OrderFailed;
use TicketKitten\Mail\OrderSummary;
use TicketKitten\Mail\OrganizerMail\OrderSummaryForOrganizer;
use TicketKitten\Repository\Eloquent\Value\Relationship;
use TicketKitten\Repository\Interfaces\EventRepositoryInterface;
use TicketKitten\Repository\Interfaces\OrderRepositoryInterface;

readonly class SendOrderDetailsService
{
    public function __construct(
        private EventRepositoryInterface $eventRepository,
        private OrderRepositoryInterface $orderRepository,
        private Mailer                   $mailer,
    )
    {
    }

    public function sendOrderSummaryAndTicketEmails(OrderDomainObject $order): void
    {
        $order = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->loadRelation(AttendeeDomainObject::class)
            ->findById($order->getId());

        $event = $this->eventRepository
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->findById($order->getEventId());

        if ($order->isOrderCompleted()) {
            $this->sendOrderSummaryEmails($order, $event);
            $this->sendAttendeeTicketEmails($order, $event);
        }

        if ($order->isOrderFailed()) {
            $this->mailer->to($order->getEmail())->send(new OrderFailed($order, $event));
        }
    }

    private function sendAttendeeTicketEmails(OrderDomainObject $order, EventDomainObject $event): void
    {
        $sentEmails = [];
        foreach ($order->getAttendees() as $attendee) {
            if (in_array($attendee->getEmail(), $sentEmails, true)) {
                continue;
            }

            $this->mailer->to($attendee->getEmail())->send(new AttendeeTicketMail($attendee, $event));
            $sentEmails[] = $attendee->getEmail();
        }
    }

    private function sendOrderSummaryEmails(OrderDomainObject $order, EventDomainObject $event): void
    {
        $this->mailer->to($order->getEmail())->send(new OrderSummary($order, $event));

        if (!$event->getEventSettings()->getNotifyOrganizerOfNewOrders() || $order->getIsManuallyCreated()) {
            return;
        }

        $this->mailer
            ->to($event->getOrganizer()->getEmail())
            ->send(new OrderSummaryForOrganizer($order, $event));
    }
}
