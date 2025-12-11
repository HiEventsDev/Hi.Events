<?php

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Helper\Currency;
use Illuminate\Config\Repository;
use Illuminate\Support\Collection;

class OrderPlatformFeePassThroughService
{
    public const PLATFORM_FEE_ID = 0;

    public static function getPlatformFeeName(): string
    {
        return __('Platform Fee');
    }

    public function __construct(
        private readonly Repository                            $config,
        private readonly OrderApplicationFeeCalculationService $applicationFeeCalculationService,
    )
    {
    }

    public function isEnabled(EventSettingDomainObject $eventSettings): bool
    {
        if (!$this->config->get('app.saas_mode_enabled')) {
            return false;
        }

        return $eventSettings->getPassPlatformFeeToBuyer();
    }

    /**
     * Calculate the platform fee for a single product price (quantity = 1).
     */
    public function calculateForProductPrice(
        AccountConfigurationDomainObject $accountConfiguration,
        EventSettingDomainObject         $eventSettings,
        float                            $priceWithFeesAndTaxes,
        string                           $currency,
    ): float
    {
        if (!$this->isEnabled($eventSettings) || $priceWithFeesAndTaxes <= 0) {
            return 0.0;
        }

        $order = (new OrderDomainObject())
            ->setCurrency($currency)
            ->setTotalGross($priceWithFeesAndTaxes);

        $orderItem = (new OrderItemDomainObject())
            ->setPrice($priceWithFeesAndTaxes)
            ->setQuantity(1)
            ->setTotalGross($priceWithFeesAndTaxes);

        $order->setOrderItems(collect([$orderItem]));

        $result = $this->applicationFeeCalculationService->calculateApplicationFee(
            $accountConfiguration,
            $order,
        );

        return $result ? Currency::round($result->netApplicationFee->toFloat()) : 0.0;
    }

    /**
     * Calculate the platform fee for an order.
     *
     * @param Collection<OrderItemDomainObject> $orderItems
     */
    public function calculateForOrder(
        AccountConfigurationDomainObject $accountConfiguration,
        EventSettingDomainObject         $eventSettings,
        Collection                       $orderItems,
        string                           $currency,
    ): float
    {
        if (!$this->isEnabled($eventSettings)) {
            return 0.0;
        }

        $totalGross = $orderItems->sum(fn(OrderItemDomainObject $item) => $item->getTotalGross());

        $order = (new OrderDomainObject())
            ->setCurrency($currency)
            ->setTotalGross($totalGross)
            ->setOrderItems($orderItems);

        $result = $this->applicationFeeCalculationService->calculateApplicationFee(
            $accountConfiguration,
            $order,
        );

        return $result ? Currency::round($result->netApplicationFee->toFloat()) : 0.0;
    }
}
