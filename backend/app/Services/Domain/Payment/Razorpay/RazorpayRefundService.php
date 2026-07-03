<?php

namespace HiEvents\Services\Domain\Payment\Razorpay;

use HiEvents\DomainObjects\Generated\RazorpayOrderDomainObjectAbstract;
use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientFactory;
use HiEvents\Values\MoneyValue;
use Psr\Log\LoggerInterface;
use Throwable;

class RazorpayRefundService
{
    public function __construct(
        private readonly RazorpayClientFactory              $razorpayClientFactory,
        private readonly RazorpayOrdersRepositoryInterface  $razorpayOrdersRepository,
        private readonly LoggerInterface                    $logger,
    ) {
    }

    /**
     * Issue a refund for a Razorpay payment.
     *
     * @throws Throwable
     */
    public function refundPayment(
        MoneyValue                $amount,
        RazorpayOrderDomainObject $razorpayOrder,
    ): void {
        if (!$razorpayOrder->getRazorpayPaymentId()) {
            throw new \RuntimeException(
                __('Cannot refund: no Razorpay payment ID found for this order.')
            );
        }

        $api = $this->razorpayClientFactory->create();

        $this->logger->info('Issuing Razorpay refund', [
            'razorpay_payment_id' => $razorpayOrder->getRazorpayPaymentId(),
            'amount_minor'        => $amount->toMinorUnit(),
        ]);

        $refund = $api->refund->create([
            'payment_id' => $razorpayOrder->getRazorpayPaymentId(),
            'amount'     => $amount->toMinorUnit(),
        ]);

        $this->logger->info('Razorpay refund issued', [
            'refund_id'           => $refund->id,
            'razorpay_payment_id' => $razorpayOrder->getRazorpayPaymentId(),
            'amount_minor'        => $amount->toMinorUnit(),
        ]);

        // Mark the razorpay order as refund pending
        $this->razorpayOrdersRepository->updateWhere(
            attributes: [RazorpayOrderDomainObjectAbstract::STATUS => 'refunded'],
            where: [RazorpayOrderDomainObjectAbstract::ID => $razorpayOrder->getId()],
        );
    }
}
