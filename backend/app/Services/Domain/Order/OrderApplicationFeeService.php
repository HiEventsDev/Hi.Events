<?php

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Generated\OrderApplicationFeeDomainObjectAbstract;
use HiEvents\DomainObjects\Status\OrderApplicationFeeStatus;
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
        float                     $applicationFeeAmount,
        OrderApplicationFeeStatus $orderApplicationFeeStatus,
        PaymentProviders          $paymentMethod,
    ): void
    {
        $this->orderApplicationFeeRepository->create([
            OrderApplicationFeeDomainObjectAbstract::ORDER_ID => $orderId,
            OrderApplicationFeeDomainObjectAbstract::AMOUNT => $applicationFeeAmount,
            OrderApplicationFeeDomainObjectAbstract::STATUS => $orderApplicationFeeStatus->value,
            OrderApplicationFeeDomainObjectAbstract::PAYMENT_METHOD => $paymentMethod->value,
            OrderApplicationFeeDomainObjectAbstract::PAID_AT => $orderApplicationFeeStatus->value === OrderApplicationFeeStatus::PAID->value
                ? now()->toDateTimeString()
                : null,
        ]);
    }
}
