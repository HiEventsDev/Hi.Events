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
        int     $applicationFeeGrossAmountMinorUnit,
        string  $currency,
        ?string $transactionId = null,
        ?string $chargeId = null,
        ?int    $applicationFeeNetAmountMinorUnit = null,
        ?int    $applicationFeeVatAmountMinorUnit = null,
        ?float  $applicationFeeVatRate = null,
    ): void
    {
        $isZeroDecimalCurrency = Currency::isZeroDecimalCurrency($currency);

        $paymentPlatformFeeAmount = $isZeroDecimalCurrency
            ? $paymentPlatformFeeAmountMinorUnit
            : $paymentPlatformFeeAmountMinorUnit / 100;

        $applicationFeeGrossAmount = $isZeroDecimalCurrency
            ? $applicationFeeGrossAmountMinorUnit
            : $applicationFeeGrossAmountMinorUnit / 100;

        $applicationFeeNetAmount = null;
        if ($applicationFeeNetAmountMinorUnit !== null) {
            $applicationFeeNetAmount = $isZeroDecimalCurrency
                ? $applicationFeeNetAmountMinorUnit
                : $applicationFeeNetAmountMinorUnit / 100;
        }

        $applicationFeeVatAmount = null;
        if ($applicationFeeVatAmountMinorUnit !== null) {
            $applicationFeeVatAmount = $isZeroDecimalCurrency
                ? $applicationFeeVatAmountMinorUnit
                : $applicationFeeVatAmountMinorUnit / 100;
        }

        $this->orderPaymentPlatformFeeRepository->create([
            OrderPaymentPlatformFeeDomainObjectAbstract::ORDER_ID => $orderId,
            OrderPaymentPlatformFeeDomainObjectAbstract::PAYMENT_PLATFORM => $paymentPlatform,
            OrderPaymentPlatformFeeDomainObjectAbstract::FEE_ROLLUP => $feeRollup,
            OrderPaymentPlatformFeeDomainObjectAbstract::PAYMENT_PLATFORM_FEE_AMOUNT => $paymentPlatformFeeAmount,
            OrderPaymentPlatformFeeDomainObjectAbstract::APPLICATION_FEE_GROSS_AMOUNT => $applicationFeeGrossAmount,
            OrderPaymentPlatformFeeDomainObjectAbstract::APPLICATION_FEE_NET_AMOUNT => $applicationFeeNetAmount,
            OrderPaymentPlatformFeeDomainObjectAbstract::APPLICATION_FEE_VAT_AMOUNT => $applicationFeeVatAmount,
            OrderPaymentPlatformFeeDomainObjectAbstract::APPLICATION_FEE_VAT_RATE => $applicationFeeVatRate,
            OrderPaymentPlatformFeeDomainObjectAbstract::CURRENCY => strtoupper($currency),
            OrderPaymentPlatformFeeDomainObjectAbstract::TRANSACTION_ID => $transactionId,
            OrderPaymentPlatformFeeDomainObjectAbstract::CHARGE_ID => $chargeId,
            OrderPaymentPlatformFeeDomainObjectAbstract::PAID_AT => now()->toDateTimeString(),
        ]);
    }
}
