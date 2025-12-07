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
            $applicationFeeGross = $this->extractApplicationFee($feeDetails);
            $paymentPlatformFee = $this->extractStripeFee($feeDetails);

            $applicationFeeBreakdown = $this->convertApplicationFeeToSettlementCurrency(
                stripePayment: $stripePayment,
                balanceTransaction: $balanceTransaction
            );

            $this->orderPaymentPlatformFeeService->createOrderPaymentPlatformFee(
                orderId: $order->getId(),
                paymentPlatform: PaymentProviders::STRIPE->value,
                feeRollup: [
                    'total_fee' => $totalFee,
                    'payment_platform_fee' => $paymentPlatformFee,
                    'application_fee' => $applicationFeeGross,
                    'fee_details' => $feeDetails,
                    'net' => $balanceTransaction->net ?? 0,
                    'gross' => $balanceTransaction->amount ?? 0,
                    'exchange_rate' => $balanceTransaction->exchange_rate ?? null,
                ],
                paymentPlatformFeeAmountMinorUnit: $paymentPlatformFee,
                applicationFeeGrossAmountMinorUnit: $applicationFeeGross,
                currency: $balanceTransaction->currency ?? $order->getCurrency(),
                transactionId: $balanceTransaction->id ?? null,
                chargeId: $charge->id ?? null,
                applicationFeeNetAmountMinorUnit: $applicationFeeBreakdown['net'],
                applicationFeeVatAmountMinorUnit: $applicationFeeBreakdown['vat'],
                applicationFeeVatRate: $stripePayment->getApplicationFeeVatRate(),
            );

            $this->logger->info(__('Platform fee stored successfully'), [
                'order_id' => $order->getId(),
                'total_fee' => $totalFee,
                'payment_platform_fee' => $paymentPlatformFee,
                'application_fee_gross' => $applicationFeeGross,
                'application_fee_net' => $applicationFeeBreakdown['net'],
                'application_fee_vat' => $applicationFeeBreakdown['vat'],
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

    /**
     * Convert application fee VAT breakdown from transaction currency to settlement currency.
     *
     * Retrieves VAT data from stripe_payments table (already stored in transaction currency, minor units)
     * and converts to settlement currency using the exchange rate from the balance transaction.
     */
    private function convertApplicationFeeToSettlementCurrency(
        StripePaymentDomainObject $stripePayment,
        $balanceTransaction
    ): array
    {
        if (!config('app.tax.eu_vat_handling_enabled')) {
            return [
                'net' => null,
                'vat' => null,
            ];
        }

        // Get VAT data from DB (transaction currency, minor units)
        $netMinor = $stripePayment->getApplicationFeeNet();
        $vatMinor = $stripePayment->getApplicationFeeVat();

        if ($netMinor === null && $vatMinor === null) {
            return [
                'net' => null,
                'vat' => null,
            ];
        }

        $exchangeRate = $balanceTransaction->exchange_rate ?? null;

        // Convert to major units (transaction currency)
        $netMajor = $netMinor !== null ? $netMinor / 100 : null;
        $vatMajor = $vatMinor !== null ? $vatMinor / 100 : null;

        // Apply exchange rate to convert to settlement currency (major units)
        $netConverted = $netMajor !== null && $exchangeRate
            ? $netMajor * $exchangeRate
            : $netMajor;
        $vatConverted = $vatMajor !== null && $exchangeRate
            ? $vatMajor * $exchangeRate
            : $vatMajor;

        // Convert to minor units (settlement currency)
        $netAmountMinorUnit = $netConverted !== null ? (int)round($netConverted * 100) : null;
        $vatAmountMinorUnit = $vatConverted !== null ? (int)round($vatConverted * 100) : null;

        return [
            'net' => $netAmountMinorUnit,
            'vat' => $vatAmountMinorUnit,
        ];
    }
}
