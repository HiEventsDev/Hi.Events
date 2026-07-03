<?php

namespace HiEvents\Services\Domain\Payment\Razorpay;

use HiEvents\DomainObjects\Generated\RazorpayOrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientFactory;
use Psr\Log\LoggerInterface;
use Throwable;

class RazorpayOrderCreationService
{
    public function __construct(
        private readonly RazorpayClientFactory               $razorpayClientFactory,
        private readonly RazorpayOrdersRepositoryInterface   $razorpayOrdersRepository,
        private readonly LoggerInterface                     $logger,
    ) {
    }

    /**
     * Create a Razorpay order and persist the record locally.
     *
     * @return array{razorpay_order_id: string, amount_minor: int, currency: string}
     * @throws Throwable
     */
    public function createOrder(OrderDomainObject $order): array
    {
        $api         = $this->razorpayClientFactory->create();
        $amountMinor = (int) round($order->getTotalGross() * 100); // convert to minor units (paise for INR)
        $currency    = strtoupper($order->getCurrency());

        $this->logger->info('Creating Razorpay order', [
            'order_id'     => $order->getId(),
            'amount_minor' => $amountMinor,
            'currency'     => $currency,
        ]);

        $razorpayOrder = $api->order->create([
            'amount'          => $amountMinor,
            'currency'        => $currency,
            'receipt'         => (string) $order->getShortId(),
            'payment_capture' => 1, // Auto-capture on payment
        ]);

        $this->razorpayOrdersRepository->create([
            RazorpayOrderDomainObjectAbstract::ORDER_ID          => $order->getId(),
            RazorpayOrderDomainObjectAbstract::RAZORPAY_ORDER_ID => $razorpayOrder->id,
            RazorpayOrderDomainObjectAbstract::AMOUNT_MINOR      => $amountMinor,
            RazorpayOrderDomainObjectAbstract::CURRENCY          => $currency,
            RazorpayOrderDomainObjectAbstract::STATUS            => 'created',
        ]);

        $this->logger->info('Razorpay order created', [
            'razorpay_order_id' => $razorpayOrder->id,
            'order_id'          => $order->getId(),
        ]);

        return [
            'razorpay_order_id' => $razorpayOrder->id,
            'amount_minor'      => $amountMinor,
            'currency'          => $currency,
        ];
    }
}
