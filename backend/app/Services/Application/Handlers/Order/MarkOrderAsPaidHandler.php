<?php

namespace HiEvents\Services\Application\Handlers\Order;

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
use HiEvents\Services\Application\Handlers\Order\DTO\MarkOrderAsPaidDTO;
use Illuminate\Database\DatabaseManager;
use Throwable;

class MarkOrderAsPaidHandler
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
     * @throws ResourceConflictException
     * @throws Throwable
     */
    public function handle(MarkOrderAsPaidDTO $dto): OrderDomainObject
    {
        return $this->databaseManager->transaction(fn() => $this->markOrderAsPaid($dto));
    }

    /**
     * @throws ResourceConflictException
     */
    private function markOrderAsPaid(MarkOrderAsPaidDTO $dto): OrderDomainObject
    {
        /** @var OrderDomainObject $order */
        $order = $this->orderRepository->findFirstWhere([
            OrderDomainObjectAbstract::ID => $dto->orderId,
            OrderDomainObjectAbstract::EVENT_ID => $dto->eventId,
        ]);

        if ($order->getStatus() !== OrderStatus::AWAITING_OFFLINE_PAYMENT->name) {
            throw new ResourceConflictException(__('Order is not awaiting offline payment'));
        }

        $this->updateOrderStatus($dto);

        $this->updateOrderInvoice($dto);

        $updatedOrder = $this->orderRepository->findById($dto->orderId);

        $this->updateAttendeeStatuses($updatedOrder);

        OrderStatusChangedEvent::dispatch($updatedOrder, false);

        return $updatedOrder;
    }

    private function updateOrderInvoice(MarkOrderAsPaidDTO $dto): void
    {
        $invoice = $this->invoiceRepository->findLatestInvoiceForOrder($dto->orderId);

        if ($invoice) {
            $this->invoiceRepository->updateFromArray($invoice->getId(), [
                'status' => InvoiceStatus::PAID->name,
            ]);
        }
    }

    private function updateOrderStatus(MarkOrderAsPaidDTO $dto): void
    {
        $this->orderRepository->updateFromArray($dto->orderId, [
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
