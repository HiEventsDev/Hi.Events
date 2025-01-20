<?php

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\InvoiceStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Throwable;

class MarkOrderAsPaidService
{
    public function __construct(
        private readonly OrderRepositoryInterface    $orderRepository,
        private readonly DatabaseManager             $databaseManager,
        private readonly InvoiceRepositoryInterface  $invoiceRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
    )
    {
    }

    /**
     * @throws ResourceConflictException|Throwable
     */
    public function markOrderAsPaid(
        int $orderId,
        int $eventId,
    ): OrderDomainObject
    {
        return $this->databaseManager->transaction(function () use ($orderId, $eventId) {
            /** @var OrderDomainObject $order */
            $order = $this->orderRepository->findFirstWhere([
                OrderDomainObjectAbstract::ID => $orderId,
                OrderDomainObjectAbstract::EVENT_ID => $eventId,
            ]);

            if ($order->getStatus() !== OrderStatus::AWAITING_OFFLINE_PAYMENT->name) {
                throw new ResourceConflictException(__('Order is not awaiting offline payment'));
            }

            $this->updateOrderStatus($orderId);

            $this->updateOrderInvoice($orderId);

            $updatedOrder = $this->orderRepository->findById($orderId);

            $this->updateAttendeeStatuses($updatedOrder);

            event(new OrderStatusChangedEvent(
                order: $updatedOrder,
                sendEmails: false
            ));

            return $updatedOrder;
        });
    }

    private function updateOrderInvoice(int $orderId): void
    {
        $invoice = $this->invoiceRepository->findLatestInvoiceForOrder($orderId);

        if ($invoice) {
            $this->invoiceRepository->updateFromArray($invoice->getId(), [
                'status' => InvoiceStatus::PAID->name,
            ]);
        }
    }

    private function updateOrderStatus(int $orderId): void
    {
        $this->orderRepository->updateFromArray($orderId, [
            OrderDomainObjectAbstract::STATUS => OrderStatus::COMPLETED->name,
            OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::PAYMENT_RECEIVED->name,
        ]);
    }

    private function updateAttendeeStatuses(OrderDomainObject $updatedOrder): void
    {
        $this->attendeeRepository->updateWhere(
            attributes: [
                'status' => AttendeeStatus::ACTIVE->name,
            ],
            where: [
                'order_id' => $updatedOrder->getId(),
                'status' => AttendeeStatus::AWAITING_PAYMENT->name,
            ],
        );
    }

}
