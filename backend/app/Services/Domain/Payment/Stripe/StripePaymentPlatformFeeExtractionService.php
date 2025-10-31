<?php

namespace HiEvents\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Generated\OrderPaymentPlatformFeeDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Repository\Interfaces\OrderPaymentPlatformFeeRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderPaymentPlatformFeeService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Psr\Log\LoggerInterface;
use Stripe\Charge;
use Throwable;

class StripePaymentPlatformFeeExtractionService
{
    public function __construct(
        private readonly StripeClientFactory                        $stripeClientFactory,
        private readonly OrderPaymentPlatformFeeService             $orderPaymentPlatformFeeService,
        private readonly OrderPaymentPlatformFeeRepositoryInterface $orderPaymentPlatformFeeRepository,
        private readonly LoggerInterface                            $logger,
    )
    {
    }

    public function extractAndStorePlatformFee(
        OrderDomainObject         $order,
        Charge                    $charge,
        StripePaymentDomainObject $stripePayment
    ): void
    {
        try {
            $this->logger->info(__('Extracting platform fee for order'), [
                'order_id' => $order->getId(),
                'charge_id' => $charge->id,
            ]);

            if (!$charge->balance_transaction || is_string($charge->balance_transaction)) {
                $this->logger->info(__('Retrieving balance transaction from Stripe'), [
                    'charge_id' => $charge->id,
                    'order_id' => $order->getId(),
                    'connected_account_id' => $stripePayment->getConnectedAccountId(),
                    'balance_transaction_type' => gettype($charge->balance_transaction),
                ]);

                $stripeClient = $this->stripeClientFactory->createForPlatform(
                    $stripePayment->getStripePlatformEnum()
                );

                $params = ['expand' => ['balance_transaction']];
                $opts = [];

                if ($stripePayment->getConnectedAccountId()) {
                    $opts['stripe_account'] = $stripePayment->getConnectedAccountId();
                }

                $charge = $stripeClient->charges->retrieve($charge->id, $params, $opts);
            }

            if (!$charge->balance_transaction || is_string($charge->balance_transaction)) {
                $this->logger->warning(__('No balance transaction found for charge'), [
                    'charge_id' => $charge->id,
                    'order_id' => $order->getId(),
                ]);
                return;
            }

            $balanceTransaction = $charge->balance_transaction;

            $existingRecord = $this->orderPaymentPlatformFeeRepository->findFirstWhere([
                OrderPaymentPlatformFeeDomainObjectAbstract::ORDER_ID => $order->getId(),
                OrderPaymentPlatformFeeDomainObjectAbstract::TRANSACTION_ID => $balanceTransaction->id,
            ]);

            if ($existingRecord) {
                $this->logger->info(__('Platform fee already stored for this transaction'), [
                    'order_id' => $order->getId(),
                    'transaction_id' => $balanceTransaction->id,
                    'charge_id' => $charge->id,
                ]);
                return;
            }
            $feeDetails = $this->extractFeeDetails($balanceTransaction);

            $totalFee = $balanceTransaction->fee ?? 0;
            $applicationFee = $this->extractApplicationFee($feeDetails);
            $paymentPlatformFee = $this->extractStripeFee($feeDetails);

            $this->orderPaymentPlatformFeeService->createOrderPaymentPlatformFee(
                orderId: $order->getId(),
                paymentPlatform: PaymentProviders::STRIPE->value,
                feeRollup: [
                    'total_fee' => $totalFee,
                    'payment_platform_fee' => $paymentPlatformFee,
                    'application_fee' => $applicationFee,
                    'fee_details' => $feeDetails,
                    'net' => $balanceTransaction->net ?? 0,
                    'exchange_rate' => $balanceTransaction->exchange_rate ?? null,
                ],
                paymentPlatformFeeAmountMinorUnit: $paymentPlatformFee,
                applicationFeeAmountMinorUnit: $applicationFee,
                currency: $balanceTransaction->currency ?? $order->getCurrency(),
                transactionId: $balanceTransaction->id ?? null,
            );

            $this->logger->info(__('Platform fee stored successfully'), [
                'order_id' => $order->getId(),
                'total_fee' => $totalFee,
                'payment_platform_fee' => $paymentPlatformFee,
                'application_fee' => $applicationFee,
                'currency' => strtoupper($balanceTransaction->currency ?? $order->getCurrency()),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error(__('Failed to store platform fee'), [
                'exception' => $exception->getMessage(),
                'order_id' => $order->getId(),
                'charge_id' => $charge->id,
            ]);

            throw $exception;
        }
    }

    private function extractFeeDetails($balanceTransaction): array
    {
        $feeDetails = [];

        if (isset($balanceTransaction->fee_details)) {
            foreach ($balanceTransaction->fee_details as $feeDetail) {
                $feeDetails[] = [
                    'type' => $feeDetail->type ?? null,
                    'amount' => $feeDetail->amount ?? 0,
                    'currency' => $feeDetail->currency ?? $balanceTransaction->currency,
                    'description' => $feeDetail->description ?? null,
                ];
            }
        }

        return $feeDetails;
    }

    private function extractStripeFee(array $feeDetails): int
    {
        foreach ($feeDetails as $detail) {
            if ($detail['type'] === 'stripe_fee') {
                return $detail['amount'];
            }
        }

        return 0;
    }

    private function extractApplicationFee(array $feeDetails): int
    {
        foreach ($feeDetails as $detail) {
            if ($detail['type'] === 'application_fee') {
                return $detail['amount'];
            }
        }

        return 0;
    }
}
