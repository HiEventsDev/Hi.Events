<?php

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Generated\OrderApplicationFeeDomainObjectAbstract;
use HiEvents\DomainObjects\Status\OrderApplicationFeeStatus;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Interfaces\OrderApplicationFeeRepositoryInterface;

class OrderApplicationFeeService
{
    public function __construct(
        private readonly OrderApplicationFeeRepositoryInterface $orderApplicationFeeRepository,
    )
    {
    }

    public function createOrderApplicationFee(
        int                       $orderId,
        int                       $applicationFeeAmountMinorUnit,
        OrderApplicationFeeStatus $orderApplicationFeeStatus,
        PaymentProviders          $paymentMethod,
        string                    $currency,
    ): void
    {
        $isZeroDecimalCurrency = Currency::isZeroDecimalCurrency($currency);

        $applicationFeeAmount = $isZeroDecimalCurrency
            ? $applicationFeeAmountMinorUnit
            : $applicationFeeAmountMinorUnit / 100;

        $this->orderApplicationFeeRepository->create([
            OrderApplicationFeeDomainObjectAbstract::ORDER_ID => $orderId,
            OrderApplicationFeeDomainObjectAbstract::AMOUNT => $applicationFeeAmount,
            OrderApplicationFeeDomainObjectAbstract::STATUS => $orderApplicationFeeStatus->value,
            OrderApplicationFeeDomainObjectAbstract::PAYMENT_METHOD => $paymentMethod->value,
            ORderApplicationFeeDomainObjectAbstract::CURRENCY => $currency,
            OrderApplicationFeeDomainObjectAbstract::PAID_AT => $orderApplicationFeeStatus->value === OrderApplicationFeeStatus::PAID->value
                ? now()->toDateTimeString()
                : null,
        ]);
    }
}
