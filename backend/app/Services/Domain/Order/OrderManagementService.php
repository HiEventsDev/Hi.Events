<?php

namespace HiEvents\Services\Domain\Order;

use Carbon\Carbon;
use HiEvents\DomainObjects\AffiliateDomainObject;
use HiEvents\DomainObjects\Enums\TaxCalculationType;
use HiEvents\DomainObjects\Enums\TaxType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Tax\TaxAndFeeCalculationService;
use HiEvents\Services\Domain\Tax\TaxAndFeeOrderRollupService;
use Illuminate\Support\Collection;

class OrderManagementService
{
    public function __construct(
        readonly private OrderRepositoryInterface    $orderRepository,
        readonly private TaxAndFeeOrderRollupService $taxAndFeeOrderRollupService,
        readonly private TaxAndFeeCalculationService $taxAndFeeCalculationService,
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
        ?AffiliateDomainObject $affiliate = null,
        ?string                $sessionId = null,
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
            'affiliate_id' => $affiliate?->getId(),
            'locale' => $locale,
        ]);
    }

    /**
     * Update order totals by summing up all order items.
     * Platform fee and its tax are included at the item level.
     * Per-order fees are applied once on top of the item totals.
     *
     * @param OrderDomainObject $order
     * @param Collection<OrderItemDomainObject> $orderItems
     * @param Collection<TaxAndFeesDomainObject> $perOrderTaxesAndFees
     * @return OrderDomainObject
     */
    public function updateOrderTotals(
        OrderDomainObject $order,
        Collection        $orderItems,
        ?Collection       $perOrderTaxesAndFees = null,
    ): OrderDomainObject
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

        $rollup = $this->taxAndFeeOrderRollupService->rollup($orderItems);

        // Apply per-order fees (once per order, not per product)
        if ($perOrderTaxesAndFees !== null && $perOrderTaxesAndFees->isNotEmpty()) {
            $perOrderFees = $perOrderTaxesAndFees->filter(fn(TaxAndFeesDomainObject $t) => $t->isFee());
            $perOrderTaxes = $perOrderTaxesAndFees->filter(fn(TaxAndFeesDomainObject $t) => $t->isTax());

            $perOrderFeeTotal = $perOrderFees->sum(function (TaxAndFeesDomainObject $fee) use ($totalBeforeAdditions) {
                return $this->calculateSingleFee($fee, $totalBeforeAdditions);
            });

            $perOrderInclusiveTaxTotal = 0.00;

            $perOrderTaxTotal = $perOrderTaxes->sum(function (TaxAndFeesDomainObject $tax) use ($totalBeforeAdditions, $perOrderFeeTotal, &$perOrderInclusiveTaxTotal) {
                $amount = $this->calculateSingleFee($tax, $totalBeforeAdditions + $perOrderFeeTotal);
                if ($tax->getIsTaxInclusive()) {
                    $perOrderInclusiveTaxTotal += $amount;
                }
                return $amount;
            });

            $totalFee += Currency::round($perOrderFeeTotal);
            $totalTax += Currency::round($perOrderTaxTotal);
            // Only add exclusive per-order taxes to gross (inclusive taxes are already in total_before_additions)
            $totalGross += Currency::round($perOrderFeeTotal + $perOrderTaxTotal - $perOrderInclusiveTaxTotal);

            // Add per-order fees to rollup
            foreach ($perOrderFees as $fee) {
                $amount = $this->calculateSingleFee($fee, $totalBeforeAdditions);
                $rollup['fees'][] = [
                    'name' => $fee->getName(),
                    'rate' => $fee->getRate(),
                    'type' => $fee->getCalculationType(),
                    'value' => Currency::round($amount),
                ];
            }
            foreach ($perOrderTaxes as $tax) {
                $amount = $this->calculateSingleFee($tax, $totalBeforeAdditions + $perOrderFeeTotal);
                $entry = [
                    'name' => $tax->getName(),
                    'rate' => $tax->getRate(),
                    'type' => $tax->getCalculationType(),
                    'value' => Currency::round($amount),
                ];
                if ($tax->getIsTaxInclusive()) {
                    $entry['is_tax_inclusive'] = true;
                }
                $rollup['taxes'][] = $entry;
            }
        }

        $this->orderRepository->updateFromArray($order->getId(), [
            'total_before_additions' => $totalBeforeAdditions,
            'total_tax' => $totalTax,
            'total_fee' => $totalFee,
            'total_gross' => Currency::round($totalGross),
            'taxes_and_fees_rollup' => $rollup,
        ]);

        return $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->findById($order->getId());
    }

    private function calculateSingleFee(TaxAndFeesDomainObject $taxOrFee, float $baseAmount): float
    {
        if ($baseAmount === 0.00) {
            return 0.00;
        }

        $isInclusive = $taxOrFee->getIsTaxInclusive();

        return match ($taxOrFee->getCalculationType()) {
            TaxCalculationType::FIXED->name => $taxOrFee->getRate(),
            TaxCalculationType::PERCENTAGE->name => $isInclusive
                ? ($baseAmount * $taxOrFee->getRate()) / (100 + $taxOrFee->getRate())
                : ($baseAmount * $taxOrFee->getRate()) / 100,
            default => 0.00,
        };
    }
}
