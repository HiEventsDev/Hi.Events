<?php

namespace HiEvents\Services\Application\Handlers\TransactionMessage;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\TransactionalEmailType;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\OutgoingTransactionMessageDomainObjectAbstract;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OutgoingTransactionMessageDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\OutgoingTransactionMessageRepositoryInterface;
use HiEvents\Services\Domain\Attendee\SendAttendeeTicketService;
use HiEvents\Services\Domain\Mail\SendOrderDetailsService;
use Illuminate\Validation\ValidationException;

class ResendTransactionMessageHandler
{
    public function __construct(
        private readonly OutgoingTransactionMessageRepositoryInterface $repository,
        private readonly OrderRepositoryInterface                     $orderRepository,
        private readonly AttendeeRepositoryInterface                  $attendeeRepository,
        private readonly EventRepositoryInterface                     $eventRepository,
        private readonly SendOrderDetailsService                      $sendOrderDetailsService,
        private readonly SendAttendeeTicketService                    $sendAttendeeTicketService,
    )
    {
    }

    /**
     * @throws ValidationException
     */
    public function handle(int $eventId, int $messageId, ?string $newEmail = null): OutgoingTransactionMessageDomainObject
    {
        $message = $this->repository->findFirstWhere([
            OutgoingTransactionMessageDomainObjectAbstract::ID => $messageId,
            OutgoingTransactionMessageDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if (!$message) {
            throw ValidationException::withMessages(['message' => [__('Transaction message not found')]]);
        }

        $emailType = TransactionalEmailType::from($message->getEmailType());

        if ($newEmail) {
            $this->updateEntityEmail($message, $emailType, $newEmail);
        }

        $this->resendByType($message, $emailType, $message->getSesMessageId(), $message->getId());

        return $this->repository->findById($messageId);
    }

    private function updateEntityEmail(
        OutgoingTransactionMessageDomainObject $message,
        TransactionalEmailType                 $emailType,
        string                                 $newEmail,
    ): void
    {
        match ($emailType) {
            TransactionalEmailType::ORDER_SUMMARY,
            TransactionalEmailType::ORDER_FAILED => $this->updateOrderEmail($message->getOrderId(), $newEmail),

            TransactionalEmailType::ATTENDEE_TICKET,
            TransactionalEmailType::WAITLIST_OFFER,
            TransactionalEmailType::WAITLIST_CONFIRMATION,
            TransactionalEmailType::WAITLIST_OFFER_EXPIRED => $this->updateAttendeeEmail($message->getAttendeeId(), $newEmail),
        };
    }

    private function updateOrderEmail(int $orderId, string $email): void
    {
        $this->orderRepository->updateWhere(
            attributes: ['email' => strtolower($email)],
            where: ['id' => $orderId],
        );
    }

    private function updateAttendeeEmail(int $attendeeId, string $email): void
    {
        $this->attendeeRepository->updateWhere(
            attributes: ['email' => strtolower($email)],
            where: ['id' => $attendeeId],
        );
    }

    private function resendByType(
        OutgoingTransactionMessageDomainObject $message,
        TransactionalEmailType                 $emailType,
        ?string                                $retryForSesMessageId = null,
        ?int                                   $retryForId = null,
    ): void
    {
        $event = $this->eventRepository
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->findById($message->getEventId());

        match ($emailType) {
            TransactionalEmailType::ORDER_SUMMARY => $this->resendOrderSummary($message, $event, $retryForSesMessageId, $retryForId),
            TransactionalEmailType::ORDER_FAILED => $this->resendOrderFailed($message, $retryForSesMessageId, $retryForId),
            TransactionalEmailType::ATTENDEE_TICKET => $this->resendAttendeeTicket($message, $event, $retryForSesMessageId, $retryForId),
            TransactionalEmailType::WAITLIST_OFFER,
            TransactionalEmailType::WAITLIST_CONFIRMATION,
            TransactionalEmailType::WAITLIST_OFFER_EXPIRED => $this->resendWaitlistEmail($emailType, $message),
        };
    }

    private function resendOrderSummary(OutgoingTransactionMessageDomainObject $message, $event, ?string $retryForSesMessageId = null, ?int $retryForId = null): void
    {
        $order = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->loadRelation(InvoiceDomainObject::class)
            ->findById($message->getOrderId());

        $this->sendOrderDetailsService->sendCustomerOrderSummary(
            order: $order,
            event: $event,
            organizer: $event->getOrganizer(),
            eventSettings: $event->getEventSettings(),
            invoice: $order->getLatestInvoice(),
            retryForSesMessageId: $retryForSesMessageId,
            retryForId: $retryForId,
        );
    }

    private function resendOrderFailed(OutgoingTransactionMessageDomainObject $message, ?string $retryForSesMessageId = null, ?int $retryForId = null): void
    {
        $order = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->loadRelation(InvoiceDomainObject::class)
            ->findById($message->getOrderId());

        $this->sendOrderDetailsService->sendOrderSummaryAndTicketEmails($order, retryForSesMessageId: $retryForSesMessageId, retryForId: $retryForId);
    }

    private function resendAttendeeTicket(OutgoingTransactionMessageDomainObject $message, $event, ?string $retryForSesMessageId = null, ?int $retryForId = null): void
    {
        $order = $this->orderRepository->findById($message->getOrderId());

        $attendee = $this->attendeeRepository->findById($message->getAttendeeId());

        $this->sendAttendeeTicketService->send(
            order: $order,
            attendee: $attendee,
            event: $event,
            eventSettings: $event->getEventSettings(),
            organizer: $event->getOrganizer(),
            retryForSesMessageId: $retryForSesMessageId,
            retryForId: $retryForId,
        );
    }

    private function resendWaitlistEmail(
        TransactionalEmailType                 $emailType,
        OutgoingTransactionMessageDomainObject $message,
    ): void
    {
        $jobClass = match ($emailType) {
            TransactionalEmailType::WAITLIST_OFFER => \HiEvents\Jobs\Waitlist\SendWaitlistOfferEmailJob::class,
            TransactionalEmailType::WAITLIST_CONFIRMATION => \HiEvents\Jobs\Waitlist\SendWaitlistConfirmationEmailJob::class,
            TransactionalEmailType::WAITLIST_OFFER_EXPIRED => \HiEvents\Jobs\Waitlist\SendWaitlistOfferExpiredEmailJob::class,
            default => null,
        };

        if (!$jobClass || !$message->getAttendeeId()) {
            return;
        }

        // Waitlist jobs need the WaitlistEntryDomainObject — look up by attendee
        $waitlistEntry = app(\HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface::class)
            ->findFirstWhere(['attendee_id' => $message->getAttendeeId()]);

        if ($waitlistEntry) {
            dispatch(new $jobClass($waitlistEntry));
        }
    }
}
