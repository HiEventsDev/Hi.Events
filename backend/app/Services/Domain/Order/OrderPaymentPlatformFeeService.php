<?php

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\Generated\OrderPaymentPlatformFeeDomainObjectAbstract;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Interfaces\OrderPaymentPlatformFeeRepositoryInterface;

class OrderPaymentPlatformFeeService
{
    public function __construct(
        private readonly OrderPaymentPlatformFeeRepositoryInterface $orderPaymentPlatformFeeRepository,
    )
    {
    }

    public function createOrderPaymentPlatformFee(
        int     $orderId,
        string  $paymentPlatform,
        ?array  $feeRollup,
        int     $paymentPlatformFeeAmountMinorUnit,
        int     $applicationFeeAmountMinorUnit,
        string  $currency,
        ?string $transactionId = null,
    ): void
    {
        $isZeroDecimalCurrency = Currency::isZeroDecimalCurrency($currency);

        $paymentPlatformFeeAmount = $isZeroDecimalCurrency
            ? $paymentPlatformFeeAmountMinorUnit
            : $paymentPlatformFeeAmountMinorUnit / 100;

        $applicationFeeAmount = $isZeroDecimalCurrency
            ? $applicationFeeAmountMinorUnit
            : $applicationFeeAmountMinorUnit / 100;

        $this->orderPaymentPlatformFeeRepository->create([
            OrderPaymentPlatformFeeDomainObjectAbstract::ORDER_ID => $orderId,
            OrderPaymentPlatformFeeDomainObjectAbstract::PAYMENT_PLATFORM => $paymentPlatform,
            OrderPaymentPlatformFeeDomainObjectAbstract::FEE_ROLLUP => $feeRollup,
            OrderPaymentPlatformFeeDomainObjectAbstract::PAYMENT_PLATFORM_FEE_AMOUNT => $paymentPlatformFeeAmount,
            OrderPaymentPlatformFeeDomainObjectAbstract::APPLICATION_FEE_AMOUNT => $applicationFeeAmount,
            OrderPaymentPlatformFeeDomainObjectAbstract::CURRENCY => strtoupper($currency),
            OrderPaymentPlatformFeeDomainObjectAbstract::TRANSACTION_ID => $transactionId,
            OrderPaymentPlatformFeeDomainObjectAbstract::PAID_AT => now()->toDateTimeString(),
        ]);
    }
}
