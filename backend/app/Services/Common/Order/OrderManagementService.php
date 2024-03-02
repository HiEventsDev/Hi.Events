<?php

namespace HiEvents\Services\Common\Order;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Common\Session\SessionIdentifierService;
use HiEvents\Services\Common\Tax\TaxAndFeeOrderRollupService;

readonly class OrderManagementService
{
    public function __construct(
        private OrderRepositoryInterface    $orderRepository,
        private SessionIdentifierService    $sessionIdentifierService,
        private TaxAndFeeOrderRollupService $taxAndFeeOrderRollupService,
    )
    {
    }

    public function deleteExistingOrders(int $eventId): void
    {
        $this->orderRepository->deleteWhere([
            OrderDomainObjectAbstract::SESSION_ID => $this->sessionIdentifierService->getIdentifier(),
            OrderDomainObjectAbstract::STATUS => OrderStatus::RESERVED->name,
            OrderDomainObjectAbstract::EVENT_ID => $eventId,
        ]);
    }

    public function createNewOrder(
        int                    $eventId,
        EventDomainObject      $event,
        int                    $timeOutMinutes,
        ?PromoCodeDomainObject $promoCode
    ): OrderDomainObject
    {
        $reservedUntil = Carbon::now()->addMinutes($timeOutMinutes);
        $publicId = Str::upper(Str::random(5));

        return $this->orderRepository->create([
            'event_id' => $eventId,
            'short_id' => IdHelper::randomPrefixedId(IdHelper::ORDER_PREFIX),
            'reserved_until' => $reservedUntil->toString(),
            'status' => OrderStatus::RESERVED->name,
            'session_id' => $this->sessionIdentifierService->getIdentifier(),
            'currency' => $event->getCurrency(),
            'public_id' => $publicId,
            'promo_code_id' => $promoCode?->getId(),
            'promo_code' => $promoCode?->getCode(),
        ]);
    }

    /**
     * @param OrderDomainObject $order
     * @param Collection<OrderItemDomainObject> $orderItems
     * @return OrderDomainObject
     */
    public function updateOrderTotals(OrderDomainObject $order, Collection $orderItems): OrderDomainObject
    {
        $totalBeforeAdditions = 0;
        $totalTax = 0;
        $totalFee = 0;
        $totalGross = 0;

        foreach ($orderItems as $item) {
            $totalBeforeAdditions += $item->getTotalBeforeAdditions();
            $totalTax += $item->getTotalTax();
            $totalFee += $item->getTotalServiceFee();
            $totalGross += $item->getTotalGross();
        }

        $this->orderRepository->updateFromArray($order->getId(), [
            'total_before_additions' => $totalBeforeAdditions,
            'total_tax' => $totalTax,
            'total_fee' => $totalFee,
            'total_gross' => $totalGross,
            'taxes_and_fees_rollup' => $this->taxAndFeeOrderRollupService->rollup($orderItems),
        ]);

        return $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->findById($order->getId());
    }
}
