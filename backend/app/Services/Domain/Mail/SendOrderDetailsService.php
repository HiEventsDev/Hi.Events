<?php

namespace HiEvents\Services\Domain\Mail;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\TransactionalEmailType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\Order\OrderFailed;
use HiEvents\Mail\Order\OrderSummary;
use HiEvents\Mail\Organizer\OrderSummaryForOrganizer;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Attendee\SendAttendeeTicketService;
use HiEvents\Services\Domain\Email\MailBuilderService;
use HiEvents\Services\Domain\Email\TransactionalEmailTrackingService;
use Illuminate\Mail\Mailer;

class SendOrderDetailsService
{
    public function __construct(
        private readonly EventRepositoryInterface           $eventRepository,
        private readonly OrderRepositoryInterface           $orderRepository,
        private readonly Mailer                             $mailer,
        private readonly SendAttendeeTicketService          $sendAttendeeTicketService,
        private readonly MailBuilderService                 $mailBuilderService,
        private readonly TransactionalEmailTrackingService  $trackingService,
    )
    {
    }

    public function sendOrderSummaryAndTicketEmails(OrderDomainObject $order, ?string $retryForSesMessageId = null, ?int $retryForId = null): void
    {
        $order = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->loadRelation(AttendeeDomainObject::class)
            ->loadRelation(InvoiceDomainObject::class)
            ->findById($order->getId());

        $event = $this->eventRepository
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->findById($order->getEventId());

        if ($order->isOrderCompleted() || $order->isOrderAwaitingOfflinePayment()) {
            $this->sendOrderSummaryEmails($order, $event);
            $this->sendAttendeeTicketEmails($order, $event);
        }

        if ($order->isOrderFailed()) {
            $mail = new OrderFailed(
                order: $order,
                event: $event,
                organizer: $event->getOrganizer(),
                eventSettings: $event->getEventSettings(),
            );

            $this->trackingService->recordAndSend(
                mailer: $this->mailer,
                recipient: $order->getEmail(),
                mail: $mail,
                emailType: TransactionalEmailType::ORDER_FAILED,
                subject: $mail->envelope()->subject,
                eventId: $event->getId(),
                orderId: $order->getId(),
                accountId: $event->getAccountId(),
                locale: $order->getLocale(),
                retryForSesMessageId: $retryForSesMessageId,
                retryForId: $retryForId,
            );
        }
    }

    public function sendCustomerOrderSummary(
        OrderDomainObject        $order,
        EventDomainObject        $event,
        OrganizerDomainObject    $organizer,
        EventSettingDomainObject $eventSettings,
        ?InvoiceDomainObject     $invoice = null,
        ?string                  $retryForSesMessageId = null,
        ?int                     $retryForId = null,
    ): void
    {
        $mail = $this->mailBuilderService->buildOrderSummaryMail(
            $order,
            $event,
            $eventSettings,
            $organizer,
            $invoice
        );

        $this->trackingService->recordAndSend(
            mailer: $this->mailer,
            recipient: $order->getEmail(),
            mail: $mail,
            emailType: TransactionalEmailType::ORDER_SUMMARY,
            subject: $mail->envelope()->subject,
            eventId: $event->getId(),
            orderId: $order->getId(),
            accountId: $event->getAccountId(),
            locale: $order->getLocale(),
            retryForSesMessageId: $retryForSesMessageId,
            retryForId: $retryForId,
        );
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

        if ($order->getIsManuallyCreated() || !$event->getEventSettings()->getNotifyOrganizerOfNewOrders()) {
            return;
        }

        $mail = new OrderSummaryForOrganizer($order, $event);

        $this->trackingService->recordAndSend(
            mailer: $this->mailer,
            recipient: $event->getOrganizer()->getEmail(),
            mail: $mail,
            emailType: TransactionalEmailType::ORGANIZER_ORDER_SUMMARY,
            subject: $mail->envelope()->subject,
            eventId: $event->getId(),
            orderId: $order->getId(),
            accountId: $event->getAccountId(),
        );
    }
}
