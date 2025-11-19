<?php

namespace HiEvents\Services\Domain\Payment\Stripe;

use HiEvents\Repository\Interfaces\StripePayoutsRepositoryInterface;
use HiEvents\Repository\Interfaces\StripePaymentsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderPaymentPlatformFeeRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\DTOs\StripePayoutCreationDTO;
use Psr\Log\LoggerInterface;

class StripePayoutService
{
    public function __construct(
        private readonly StripePayoutsRepositoryInterface $stripePayoutsRepository,
        private readonly StripePaymentsRepositoryInterface $stripePaymentsRepository,
        private readonly OrderPaymentPlatformFeeRepositoryInterface $orderPaymentPlatformFeeRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createOrUpdatePayout(StripePayoutCreationDTO $dto): void
    {
        $this->logger->info('Creating/updating stripe_payouts record', ['payout_id' => $dto->payoutId]);

        $payments = $this->stripePaymentsRepository->findWhere([
            'payout_id' => $dto->payoutId,
        ]);

        if ($payments->isEmpty()) {
            $this->logger->warning('No payments found for payout', ['payout_id' => $dto->payoutId]);
            return;
        }

        // Get charge IDs to query order_payment_platform_fees
        $chargeIds = $payments
            ->map(fn($payment) => $payment->getChargeId())
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($chargeIds)) {
            $this->logger->warning('No charge IDs found for payout payments', ['payout_id' => $dto->payoutId]);
            return;
        }

        $this->logger->debug('Fetching platform fees for payout', [
            'payout_id' => $dto->payoutId,
            'charge_ids' => $chargeIds,
        ]);

        // Get settlement currency VAT/net from order_payment_platform_fees
        $platformFees = $this->orderPaymentPlatformFeeRepository->findWhereIn('charge_id', $chargeIds);

        $this->logger->debug('Found platform fees for payout', [
            'payout_id' => $dto->payoutId,
            'platform_fees_count' => $platformFees->count(),
        ]);

        if ($platformFees->isEmpty()) {
            $this->logger->warning('No platform fees found for payout', ['payout_id' => $dto->payoutId]);

            // Still create the payout record without VAT data
            $this->createOrUpdatePayoutRecord($dto, null, null, false);
            return;
        }

        $totalVatMinor = 0;
        $totalNetMinor = 0;
        $foundVat = false;
        $settlementCurrency = null;

        foreach ($platformFees as $platformFee) {
            $vatMajor = $platformFee->getApplicationFeeVatAmount();
            $netMajor = $platformFee->getApplicationFeeNetAmount();

            $this->logger->debug('Processing platform fee for payout', [
                'payout_id' => $dto->payoutId,
                'charge_id' => $platformFee->getChargeId(),
                'vat_major' => $vatMajor,
                'net_major' => $netMajor,
            ]);

            if ($vatMajor === null && $netMajor === null) {
                continue;
            }

            $foundVat = true;

            // Get settlement currency from platform fee
            $settlementCurrency = $settlementCurrency ?? strtoupper($platformFee->getCurrency() ?? '');
            $payoutCurrency = strtoupper($dto->currency ?? '');

            // Only handle cases where settlement and payout currencies match
            if ($settlementCurrency !== $payoutCurrency) {
                $this->logger->error('Payout currency differs from settlement currency - VAT aggregation not supported', [
                    'payout_id' => $dto->payoutId,
                    'settlement_currency' => $settlementCurrency,
                    'payout_currency' => $payoutCurrency,
                    'charge_id' => $platformFee->getChargeId(),
                ]);
                continue; // Skip this payment
            }

            // Simple conversion: settlement currency (major units) → payout currency (minor units)
            // Since currencies match, just multiply by 100
            if ($vatMajor !== null) {
                $totalVatMinor += (int)round($vatMajor * 100);
            }

            if ($netMajor !== null) {
                $totalNetMinor += (int)round($netMajor * 100);
            }
        }

        $this->createOrUpdatePayoutRecord($dto, $totalVatMinor, $totalNetMinor, $foundVat);
    }

    private function createOrUpdatePayoutRecord(
        StripePayoutCreationDTO $dto,
        ?int $totalVatMinor,
        ?int $totalNetMinor,
        bool $reconciled
    ): void
    {
        $attributes = [
            'payout_id' => $dto->payoutId,
            'stripe_platform' => $dto->stripePlatform,
            'amount_minor' => $dto->amountMinor,
            'currency' => strtoupper($dto->currency ?? ''),
            'payout_date' => $dto->payoutDate?->format('Y-m-d H:i:s'),
            'payout_status' => $dto->status,
            'total_application_fee_vat_minor' => $totalVatMinor,
            'total_application_fee_net_minor' => $totalNetMinor,
            'metadata' => $dto->metadata,
            'reconciled' => $reconciled,
        ];

        $existing = $this->stripePayoutsRepository->findFirstByField('payout_id', $dto->payoutId);
        if ($existing) {
            $this->stripePayoutsRepository->updateFromArray($existing->getId(), $attributes);
        } else {
            $this->stripePayoutsRepository->create($attributes);
        }

        $this->logger->info('Stripe payout stored', [
            'payout_id' => $dto->payoutId,
            'vat_minor' => $attributes['total_application_fee_vat_minor'],
            'currency' => $attributes['currency'],
            'reconciled' => $reconciled,
        ]);
    }
}
