<?php

namespace HiEvents\Services\Application\Handlers\Order\Public;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Mail\Organizer\OfflinePaymentReportedMail;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Mail\TeamOrderNotificationRecipientService;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class ReportOfflinePaymentPublicHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly TeamOrderNotificationRecipientService $teamRecipientService,
        private readonly TransactionLockService $transactionLockService,
        private readonly DatabaseManager $databaseManager,
        private readonly Mailer $mailer,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $eventId, string $orderShortId, ?string $note): OrderDomainObject
    {
        return $this->databaseManager->transaction(function () use ($eventId, $orderShortId, $note) {
            $this->transactionLockService->lockOrder($orderShortId);

            $order = $this->orderRepository->findFirstWhere([
                OrderDomainObjectAbstract::EVENT_ID => $eventId,
                OrderDomainObjectAbstract::SHORT_ID => $orderShortId,
            ]);

            if ($order === null) {
                throw new ResourceNotFoundException(__('Order not found'));
            }

            $event = $this->eventRepository
                ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
                ->loadRelation(new Relationship(EventSettingDomainObject::class))
                ->findById($eventId);

            $this->validate($order, $event->getEventSettings());

            $order = $this->orderRepository->updateFromArray($order->getId(), [
                OrderDomainObjectAbstract::OFFLINE_PAYMENT_REPORTED_AT => Carbon::now('UTC')->toDateTimeString(),
                OrderDomainObjectAbstract::OFFLINE_PAYMENT_NOTE => $note,
            ]);

            $this->notifyOrganizer($order, $event);

            return $order;
        });
    }

    /**
     * @throws ResourceConflictException
     */
    private function validate(OrderDomainObject $order, EventSettingDomainObject $settings): void
    {
        if (! $settings->getOfflinePaymentReportingEnabled()) {
            throw new ResourceConflictException(__('Payment reporting is not available for this event'));
        }

        if (! $order->isOrderAwaitingOfflinePayment()) {
            throw new ResourceConflictException(__('This order is not awaiting payment'));
        }

        if ($order->getOfflinePaymentReportedAt() !== null) {
            throw new ResourceConflictException(__('You have already told the organizer about this payment'));
        }
    }

    private function notifyOrganizer(OrderDomainObject $order, EventDomainObject $event): void
    {
        $notifiesOrganizer = $event->getEventSettings()->getNotifyOrganizerOfNewOrders();

        if ($notifiesOrganizer) {
            $this->mailer
                ->to($event->getOrganizer()->getEmail())
                ->send(new OfflinePaymentReportedMail($order, $event));
        }

        $teamRecipients = $this->teamRecipientService->recipientsFor(
            event: $event,
            excludeEmail: $notifiesOrganizer ? $event->getOrganizer()->getEmail() : null,
        );

        foreach ($teamRecipients as $user) {
            $this->mailer
                ->to($user->getEmail())
                ->locale($user->getLocale())
                ->send(new OfflinePaymentReportedMail($order, $event));
        }
    }
}
