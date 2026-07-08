<?php

namespace HiEvents\Services\Domain\Payment\Razorpay;

use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientFactory;
use Illuminate\Log\Logger;
use Razorpay\Api\Errors\BadRequestError;

class RazorpayPaymentRefundService
{
    public function __construct(
        private RazorpayClientFactory $clientFactory,
        private Logger $logger
    ) {}

    public function refundPayment(
        RazorpayOrderDomainObject $payment,
        int $amountInPaise,
        ?string $idempotencyKey = null,
        array $options = []
    ): object {
        // Validate payment ID exists
        $paymentId = $payment->getRazorpayPaymentId();
        if (!$paymentId) {
            throw new RefundNotPossibleException(__('No Razorpay payment ID found for this order.'));
        }

        try {
            $client = $this->clientFactory->create();
            $params = array_merge(
                ['payment_id' => $paymentId, 'amount' => $amountInPaise],
                $options
            );
            return $client->refundPayment($params, $idempotencyKey);
        } catch (BadRequestError $e) {
            // Handle insufficient balance error
            if (str_contains($e->getMessage(), 'enough balance')) {
                $this->logger->error('Razorpay refund failed: insufficient balance', [
                    'payment_id' => $paymentId,
                    'amount' => $amountInPaise,
                    'error' => $e->getMessage()
                ]);

                throw new RefundNotPossibleException(
                    __('Refund failed due to insufficient account balance. Please add funds to your Razorpay account or try again later.')
                );
            }

            throw $e;
        }
    }
}