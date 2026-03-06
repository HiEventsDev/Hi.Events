<?php

namespace HiEvents\Services\Domain\Payment;

use HiEvents\DomainObjects\RefundAttemptDomainObject;
use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Repository\Interfaces\RefundAttemptRepositoryInterface;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayPaymentRefundService;
use HiEvents\Values\MoneyValue;
use Illuminate\Database\ConnectionInterface;
use Throwable;

class IdempotentRefundService
{
    public function __construct(
        private readonly RefundAttemptRepositoryInterface $attemptRepository,
        private readonly RazorpayPaymentRefundService $refundService,
        private readonly ConnectionInterface $dbManager,
    ) {
    }

    /**
     * @throws Throwable
     * @throws RefundNotPossibleException
     */
    public function refundWithIdempotency(
        object $payment,
        MoneyValue $amount,
        array $options,
        ?string $idempotencyKey = null // Make nullable
    ): object {
        // If no idempotency key provided, skip idempotency and refund directly
        if ($idempotencyKey === null) {
            return $this->refundService->refundPayment(
                $payment,
                $amount->toMinorUnit(),
                null,
                $options
            );
        }

        return $this->dbManager->transaction(function () use ($payment, $amount, $options, $idempotencyKey) {
            // Lock the attempt row if exists
            $attempt = $this->attemptRepository
                ->findByIdempotencyKey($idempotencyKey);

            if ($attempt) {
                return $this->handleExistingAttempt($attempt);
            }

            // Create new attempt
            $paymentId = $payment->getId();
            $paymentType = $this->getPaymentType($payment);
            $attempt = $this->attemptRepository->createAttempt(
                $idempotencyKey,
                $paymentId,
                $paymentType,
                ['amount' => $amount->toMinorUnit(), 'options' => $options]
            );

            try {
                $result = $this->refundService->refundPayment(
                    $payment,
                    $amount->toMinorUnit(),
                    $idempotencyKey,
                    $options
                );

                $this->attemptRepository->markSucceeded($attempt->getId(), (array) $result);
                return $result;
            } catch (Throwable $e) {
                $this->attemptRepository->markFailed($attempt->getId(), ['error' => $e->getMessage()]);
                throw $e;
            }
        });
    }

    private function handleExistingAttempt(RefundAttemptDomainObject $attempt): object
    {
        if ($attempt->getStatus() === 'succeeded') {
            return (object) $attempt->getResponseData();
        }

        if ($attempt->getStatus() === 'failed') {
            $this->attemptRepository->incrementAttempts($attempt->getId());
            throw new RefundNotPossibleException('Previous refund attempt failed. Please retry.');
        }

        throw new RefundNotPossibleException('A refund for this order is already being processed.');
    }

    private function getPaymentType(object $payment): string
    {
        return match (true) {
            $payment instanceof \HiEvents\DomainObjects\StripePaymentDomainObject => 'stripe',
            $payment instanceof \HiEvents\DomainObjects\RazorpayOrderDomainObject => 'razorpay',
            default => throw new \InvalidArgumentException('Unknown payment type'),
        };
    }
}