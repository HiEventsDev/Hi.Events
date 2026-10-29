<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\BoxOfficeTender;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Enterprise\BoxOffice\Exceptions\BoxOfficeSaleExpiredException;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedOrderCompletionGuard;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Order\OccurrenceStatusValidator;
use HiEvents\Services\Domain\Order\OfflineApplicationFeeRecordService;
use HiEvents\Services\Domain\Order\OrderManagementService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use HiEvents\Values\MoneyValue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class BoxOfficeOrderCompletionService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderItemRepositoryInterface $orderItemRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly OrderManagementService $orderManagementService,
        private readonly OccurrenceStatusValidator $occurrenceStatusValidator,
        private readonly ProductQuantityUpdateService $productQuantityUpdateService,
        private readonly DomainEventDispatcherService $domainEventDispatcherService,
        private readonly OfflineApplicationFeeRecordService $offlineApplicationFeeRecordService,
        private readonly SeatedOrderCompletionGuard $seatedOrderCompletionGuard,
        private readonly TransactionLockService $transactionLockService,
    ) {}

    /**
     * @throws ResourceConflictException
     * @throws BoxOfficeSaleExpiredException
     * @throws ValidationException
     */
    public function completeOffline(
        OrderDomainObject $order,
        EventDomainObject $event,
        BoxOfficeTender $tender,
        ?float $amountTendered,
        ?string $reference,
        ?string $releasedPaymentIntentId,
    ): OrderDomainObject {
        $completedOrder = $this->databaseManager->transaction(function () use ($order, $tender, $amountTendered, $reference, $releasedPaymentIntentId) {
            $this->transactionLockService->lockOrder($order->getShortId());

            $order = $this->reload($order);
            $this->assertCompletable($order);
            $this->assertNoCardPaymentStarted($order, $releasedPaymentIntentId);
            $this->seatedOrderCompletionGuard->assertSeatsHeld($order);

            $resolvedTender = $this->resolveTender($order, $tender);

            if ($resolvedTender === BoxOfficeTender::COMP) {
                $order = $this->zeroOrderTotals($order);
            }

            $tenderAttributes = $this->tenderAttributes($order, $resolvedTender, $amountTendered, $reference);

            $isFree = in_array($resolvedTender, [BoxOfficeTender::FREE, BoxOfficeTender::COMP], true);

            $this->orderRepository->updateFromArray($order->getId(), array_merge([
                OrderDomainObjectAbstract::STATUS => OrderStatus::COMPLETED->name,
                OrderDomainObjectAbstract::PAYMENT_STATUS => $isFree
                    ? OrderPaymentStatus::NO_PAYMENT_REQUIRED->name
                    : OrderPaymentStatus::PAYMENT_RECEIVED->name,
                OrderDomainObjectAbstract::PAYMENT_PROVIDER => $isFree ? null : PaymentProviders::OFFLINE->value,
                OrderDomainObjectAbstract::BOX_OFFICE_TENDER => $resolvedTender->value,
                OrderDomainObjectAbstract::BOX_OFFICE_COMPLETED_AT => now()->toDateTimeString(),
            ], $tenderAttributes));

            $this->attendeeRepository->updateWhere(
                attributes: ['status' => AttendeeStatus::ACTIVE->name],
                where: ['order_id' => $order->getId(), 'status' => AttendeeStatus::AWAITING_PAYMENT->name],
            );

            $this->productQuantityUpdateService->updateQuantitiesFromOrder($order);

            $completed = $this->reload($order);

            if ($resolvedTender !== BoxOfficeTender::FREE) {
                $this->offlineApplicationFeeRecordService->record($completed);
            }

            return $completed;
        });

        event(new OrderStatusChangedEvent(
            order: $completedOrder,
            sendEmails: $completedOrder->getEmail() !== null,
            createInvoice: (bool) $event->getEventSettings()?->getEnableInvoicing() && $completedOrder->getEmail() !== null,
        ));

        $this->domainEventDispatcherService->dispatch(
            new OrderEvent(type: DomainEventType::ORDER_CREATED, orderId: $completedOrder->getId()),
        );

        return $completedOrder;
    }

    private function reload(OrderDomainObject $order): OrderDomainObject
    {
        $reloaded = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->loadRelation(AttendeeDomainObject::class)
            ->loadRelation(new Relationship(StripePaymentDomainObject::class, name: 'stripe_payment'))
            ->findById($order->getId());

        $this->occurrenceStatusValidator->assertOrderOccurrencesArePurchasable($reloaded, allowPastOccurrence: true);

        return $reloaded;
    }

    /**
     * @throws ResourceConflictException
     * @throws BoxOfficeSaleExpiredException
     */
    private function assertCompletable(OrderDomainObject $order): void
    {
        if ($order->getStatus() !== OrderStatus::RESERVED->name) {
            throw new ResourceConflictException(__('This sale has already been completed or cancelled'));
        }

        if ($order->getReservedUntil() !== null && Carbon::parse($order->getReservedUntil())->isPast()) {
            throw new BoxOfficeSaleExpiredException(__('This sale has expired. Start a new sale.'));
        }
    }

    /**
     * @throws ResourceConflictException
     */
    private function assertNoCardPaymentStarted(OrderDomainObject $order, ?string $releasedPaymentIntentId): void
    {
        if ($order->getStripePayment()?->getPaymentIntentId() !== $releasedPaymentIntentId) {
            throw new ResourceConflictException(__('A card payment was started on this sale. Try again.'));
        }
    }

    private function zeroOrderTotals(OrderDomainObject $order): OrderDomainObject
    {
        foreach ($order->getOrderItems() as $item) {
            $this->orderItemRepository->updateFromArray($item->getId(), [
                'price_before_discount' => $item->getPriceBeforeDiscount() ?? $item->getPrice(),
                'price' => 0,
                'total_before_additions' => 0,
                'total_tax' => 0,
                'total_service_fee' => 0,
                'total_gross' => 0,
                'taxes_and_fees_rollup' => [],
            ]);
        }

        $items = $this->orderItemRepository->findWhere(['order_id' => $order->getId()]);

        return $this->orderManagementService->updateOrderTotals($order, $items);
    }

    public function resolveTender(OrderDomainObject $order, BoxOfficeTender $tender): BoxOfficeTender
    {
        return (float) $order->getTotalGross() === 0.0 ? BoxOfficeTender::FREE : $tender;
    }

    /**
     * @throws ValidationException
     */
    private function tenderAttributes(
        OrderDomainObject $order,
        BoxOfficeTender $tender,
        ?float $amountTendered,
        ?string $reference,
    ): array {
        if ($tender === BoxOfficeTender::CASH) {
            $total = MoneyValue::fromFloat((float) $order->getTotalGross(), $order->getCurrency());
            $tendered = MoneyValue::fromFloat($amountTendered ?? 0.0, $order->getCurrency());

            if ($tendered->toMinorUnit() < $total->toMinorUnit()) {
                throw ValidationException::withMessages([
                    'amount_tendered' => __('The amount tendered is less than the total'),
                ]);
            }

            return [
                OrderDomainObjectAbstract::BOX_OFFICE_AMOUNT_TENDERED => $tendered->toFloat(),
                OrderDomainObjectAbstract::BOX_OFFICE_CHANGE_DUE => MoneyValue::fromMinorUnit(
                    $tendered->toMinorUnit() - $total->toMinorUnit(),
                    $order->getCurrency(),
                )->toFloat(),
            ];
        }

        if ($tender === BoxOfficeTender::OTHER) {
            return [OrderDomainObjectAbstract::BOX_OFFICE_REFERENCE => $reference];
        }

        return [];
    }
}
