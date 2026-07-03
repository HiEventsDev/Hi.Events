<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\EventHandlers;

use HiEvents\DomainObjects\Generated\RazorpayOrderDomainObjectAbstract;
use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayPaymentCompletionService;
use Psr\Log\LoggerInterface;
use Throwable;

class PaymentCapturedHandler
{
    public function __construct(
        private readonly RazorpayOrdersRepositoryInterface  $razorpayOrdersRepository,
        private readonly RazorpayPaymentCompletionService   $paymentCompletionService,
        private readonly LoggerInterface                    $logger,
    ) {
    }

    /**
     * Handle a Razorpay `payment.captured` webhook event.
     *
     * @param array $payload The decoded webhook event payload
     * @throws Throwable
     */
    public function handle(array $payload): void
    {
        $paymentEntity    = $payload['payload']['payment']['entity'] ?? [];
        $razorpayOrderId  = $paymentEntity['order_id'] ?? null;
        $razorpayPaymentId = $paymentEntity['id'] ?? null;

        if (!$razorpayOrderId || !$razorpayPaymentId) {
            $this->logger->error('Razorpay payment.captured webhook missing order_id or payment id', [
                'payload' => $payload,
            ]);
            return;
        }

        /** @var RazorpayOrderDomainObject|null $razorpayOrder */
        $razorpayOrder = $this->razorpayOrdersRepository->findFirstWhere([
            RazorpayOrderDomainObjectAbstract::RAZORPAY_ORDER_ID => $razorpayOrderId,
        ]);

        if (!$razorpayOrder) {
            $this->logger->warning('Razorpay order not found for payment.captured event', [
                'razorpay_order_id'   => $razorpayOrderId,
                'razorpay_payment_id' => $razorpayPaymentId,
            ]);
            return;
        }

        $this->paymentCompletionService->completePayment(
            razorpayOrder:     $razorpayOrder,
            razorpayPaymentId: $razorpayPaymentId,
            razorpaySignature: '', // Webhook doesn't include the callback signature
        );
    }
}
