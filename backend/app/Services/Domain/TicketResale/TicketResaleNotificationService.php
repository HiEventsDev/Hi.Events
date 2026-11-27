<?php

namespace HiEvents\Services\Domain\TicketResale;

use HiEvents\DomainObjects\Enums\TicketResaleNotice;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\TicketResaleListingDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Helper\Url;
use HiEvents\Mail\TicketResale\TicketResaleNoticeMail;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Contracts\Mail\Mailer;

class TicketResaleNotificationService
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
    ) {}

    public function notifySeller(TicketResaleNotice $notice, TicketResaleListingDomainObject $listing, ?float $amount = null): void
    {
        $order = $this->orderRepository->findById($listing->getOrderId());

        if ($order->getEmail() === null) {
            return;
        }

        $event = $this->eventWithOrganizer($listing->getEventId());

        $this->mailer
            ->to($order->getEmail())
            ->locale($order->getLocale())
            ->send($this->mail(
                notice: $notice,
                event: $event,
                listing: $listing,
                order: $order,
                amount: $amount,
                actionUrl: sprintf(
                    Url::getFrontEndUrlFromConfig(Url::ORDER_SUMMARY),
                    $event->getId(),
                    $order->getShortId(),
                ),
                actionLabel: __('View your order'),
            ));
    }

    public function notifyHolder(TicketResaleNotice $notice, TicketResaleListingDomainObject $listing): void
    {
        $order = $this->orderRepository->findById($listing->getOrderId());
        $attendee = $this->attendeeRepository->includeDeleted()->findById($listing->getAttendeeId());

        if ($attendee->getEmail() === null
            || mb_strtolower(trim($attendee->getEmail())) === mb_strtolower(trim((string) $order->getEmail()))) {
            return;
        }

        $event = $this->eventWithOrganizer($listing->getEventId());

        $this->mailer
            ->to($attendee->getEmail())
            ->locale($attendee->getLocale())
            ->send($this->mail(
                notice: $notice,
                event: $event,
                listing: $listing,
                order: $order,
                amount: null,
                actionUrl: sprintf(
                    Url::getFrontEndUrlFromConfig(Url::ATTENDEE_TICKET),
                    $event->getId(),
                    $attendee->getShortId(),
                ),
                actionLabel: __('View your ticket'),
            ));
    }

    public function notifyOrganizerOfFailedRefund(TicketResaleListingDomainObject $listing): void
    {
        $event = $this->eventWithOrganizer($listing->getEventId());

        $this->mailer
            ->to($event->getOrganizer()->getEmail())
            ->send($this->mail(
                notice: TicketResaleNotice::REFUND_FAILED_FOR_ORGANIZER,
                event: $event,
                listing: $listing,
                order: $this->orderRepository->findById($listing->getOrderId()),
                amount: $listing->getRefundAmount(),
                actionUrl: sprintf(Url::getFrontEndUrlFromConfig(Url::EVENT_RESALE), $event->getId()),
                actionLabel: __('Open ticket resale'),
            ));
    }

    private function mail(
        TicketResaleNotice $notice,
        EventDomainObject $event,
        TicketResaleListingDomainObject $listing,
        OrderDomainObject $order,
        ?float $amount,
        string $actionUrl,
        string $actionLabel,
    ): TicketResaleNoticeMail {
        return new TicketResaleNoticeMail(
            notice: $notice,
            event: $event,
            organizer: $event->getOrganizer(),
            eventSettings: $event->getEventSettings(),
            ticketTitle: $this->attendeeRepository
                ->includeDeleted()
                ->loadRelation(new Relationship(domainObject: ProductDomainObject::class, name: 'product'))
                ->findById($listing->getAttendeeId())
                ->getProduct()
                ?->getTitle() ?? '',
            formattedAmount: $amount === null ? null : Currency::format($amount, $order->getCurrency()),
            actionUrl: $actionUrl,
            actionLabel: $actionLabel,
        );
    }

    private function eventWithOrganizer(int $eventId): EventDomainObject
    {
        return $this->eventRepository
            ->loadRelation(EventSettingDomainObject::class)
            ->loadRelation(new Relationship(domainObject: OrganizerDomainObject::class, name: 'organizer'))
            ->findById($eventId);
    }
}
