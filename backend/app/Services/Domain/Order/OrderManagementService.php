<?php

namespace HiEvents\Services\Domain\Order;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Tax\TaxAndFeeOrderRollupService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrderManagementService
{
    public function __construct(
        readonly private OrderRepositoryInterface    $orderRepository,
        readonly private TaxAndFeeOrderRollupService $taxAndFeeOrderRollupService,
    )
    {
    }

    public function deleteExistingOrders(int $eventId, string $sessionId): void
    {
        $this->orderRepository->deleteWhere([
            OrderDomainObjectAbstract::SESSION_ID => $sessionId,
            OrderDomainObjectAbstract::STATUS => OrderStatus::RESERVED->name,
            OrderDomainObjectAbstract::EVENT_ID => $eventId,
        ]);
    }

    public function createNewOrder(
        int                    $eventId,
        EventDomainObject      $event,
        int                    $timeOutMinutes,
        string                 $locale,
        ?PromoCodeDomainObject $promoCode,
        string                 $sessionId = null,
    ): OrderDomainObject
    {
        $reservedUntil = Carbon::now()->addMinutes($timeOutMinutes);

        return $this->orderRepository->create([
            'event_id' => $eventId,
            'short_id' => IdHelper::shortId(IdHelper::ORDER_PREFIX),
            'reserved_until' => $reservedUntil->toString(),
            'status' => OrderStatus::RESERVED->name,
            'session_id' => $sessionId,
            'currency' => $event->getCurrency(),
            'public_id' => IdHelper::publicId(IdHelper::ORDER_PREFIX),
            'promo_code_id' => $promoCode?->getId(),
            'promo_code' => $promoCode?->getCode(),
            'locale' => $locale,
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
