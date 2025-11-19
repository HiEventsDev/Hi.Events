<?php

namespace HiEvents\Services\Domain\Payment\Stripe\EventHandlers;

use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use HiEvents\Services\Infrastructure\Stripe\StripeConfigurationService;
use HiEvents\Services\Domain\Payment\Stripe\StripePayoutService;
use HiEvents\Services\Domain\Payment\Stripe\DTOs\StripePayoutCreationDTO;
use Psr\Log\LoggerInterface;
use Stripe\ApplicationFee;
use Stripe\Payout;
use Throwable;

class PayoutPaidHandler
{
    private const PAGE_LIMIT = 100;

    public function __construct(
        private readonly StripePaymentsRepository   $stripePaymentsRepository,
        private readonly StripeClientFactory        $stripeClientFactory,
        private readonly LoggerInterface            $logger,
        private readonly StripeConfigurationService $stripeConfigurationService,
        private readonly StripePayoutService        $stripePayoutService,
    )
    {
    }

    public function handleEvent(Payout $payout): void
    {
        try {
            $this->logger->info('Processing payout.paid event', [
                'payout_id' => $payout->id,
                'amount' => $payout->amount,
                'currency' => $payout->currency,
                'status' => $payout->status,
            ]);

            if ($payout->status !== 'paid') {
                $this->logger->info('Payout not in paid status, skipping', [
                    'payout_id' => $payout->id,
                    'status' => $payout->status,
                ]);
                return;
            }

            $stripeClient = $this->stripeClientFactory->createForPlatform(
                platform: $this->stripeConfigurationService->getPrimaryPlatform()
            );

            $page = 1;
            $reconciledCount = 0;
            $notFoundCount = 0;
            $lastId = null;

            do {
                $params = [
                    'payout' => $payout->id,
                    'limit' => self::PAGE_LIMIT,
                    'expand' => ['data.source'],
                ];

                if ($lastId) {
                    $params['starting_after'] = $lastId;
                }

                $transactions = $stripeClient->balanceTransactions->all($params);
                $this->logger->debug("Fetched page $page of payout transactions", [
                    'payout_id' => $payout->id,
                    'count' => count($transactions->data),
                ]);

                $applicationFeeTxns = collect($transactions->data)
                    ->filter(fn($txn) => $txn->type === 'application_fee' && $txn->source instanceof ApplicationFee)
                    ->values();

                if ($applicationFeeTxns->isEmpty()) {
                    $this->logger->debug('No application_fee transactions found for this page');
                    $lastId = count($transactions->data) ? end($transactions->data)->id : null;
                    $page++;
                    continue;
                }

                $chargeIds = $applicationFeeTxns
                    ->map(fn($txn) => $txn->source->originating_transaction ?? $txn->source->charge ?? $txn->source->fee_source->charge ?? null)
                    ->filter()
                    ->unique()
                    ->values();

                if ($chargeIds->isEmpty()) {
                    $this->logger->debug('No valid charge IDs found for this payout page');
                    $lastId = count($transactions->data) ? end($transactions->data)->id : null;
                    $page++;
                    continue;
                }

                // Create mapping of charge ID to balance transaction data
                $chargeToTxnData = [];
                foreach ($applicationFeeTxns as $txn) {
                    $chargeId = $txn->source->originating_transaction ?? $txn->source->charge ?? $txn->source->fee_source->charge ?? null;
                    if ($chargeId) {
                        $chargeToTxnData[$chargeId] = [
                            'balance_transaction_id' => $txn->id,
                            'payout_stripe_fee' => abs($txn->fee ?? 0),
                            'payout_net_amount' => $txn->net ?? null,
                            'payout_currency' => strtoupper($txn->currency ?? ''),
                            'payout_exchange_rate' => $txn->exchange_rate ? (float)$txn->exchange_rate : null,
                        ];
                    }
                }

                $payments = $this->stripePaymentsRepository
                    ->findWhereIn(StripePaymentDomainObjectAbstract::CHARGE_ID, $chargeIds->toArray());

                $foundPayments = $payments
                    ->filter(fn($payment) => $payment->getChargeId() !== null);

                $this->logger->debug('Found matching Stripe payments for payout reconciliation', [
                    'payout_id' => $payout->id,
                    'found_count' => $foundPayments->count(),
                    'total_charge_ids' => $chargeIds->count(),
                ]);

                $foundChargeIds = $foundPayments->map(fn($payment) => $payment->getChargeId())->values();
                $missing = $chargeIds->diff($foundChargeIds);

                if ($missing->isNotEmpty()) {
                    foreach ($missing as $missingId) {
                        $this->logger->warning('Stripe payment not found for charge in payout reconciliation', [
                            'charge_id' => $missingId,
                            'payout_id' => $payout->id,
                        ]);
                        $notFoundCount++;
                    }
                }

                // Update each payment with payout reconciliation data
                foreach ($foundPayments as $payment) {
                    $chargeId = $payment->getChargeId();
                    $txnData = $chargeToTxnData[$chargeId] ?? null;

                    if (!$txnData) {
                        continue;
                    }

                    $updateData = [
                        StripePaymentDomainObjectAbstract::PAYOUT_ID => $payout->id,
                        StripePaymentDomainObjectAbstract::BALANCE_TRANSACTION_ID => $txnData['balance_transaction_id'],
                        StripePaymentDomainObjectAbstract::PAYOUT_STRIPE_FEE => $txnData['payout_stripe_fee'],
                        StripePaymentDomainObjectAbstract::PAYOUT_NET_AMOUNT => $txnData['payout_net_amount'],
                        StripePaymentDomainObjectAbstract::PAYOUT_CURRENCY => $txnData['payout_currency'],
                        StripePaymentDomainObjectAbstract::PAYOUT_EXCHANGE_RATE => $txnData['payout_exchange_rate'],
                    ];

                    $this->stripePaymentsRepository->updateWhere(
                        $updateData,
                        [StripePaymentDomainObjectAbstract::ID => $payment->getId()]
                    );

                    $reconciledCount++;
                }

                $lastId = count($transactions->data) ? end($transactions->data)->id : null;
                $page++;

                usleep(500000); // 0.5 second delay to avoid hitting rate limits
            } while ($transactions->has_more);

            $this->logger->info('Payout reconciliation completed', [
                'payout_id' => $payout->id,
                'reconciled_count' => $reconciledCount,
                'not_found_count' => $notFoundCount,
                'total_pages' => $page - 1,
            ]);

            $dto = new StripePayoutCreationDTO(
                payoutId: $payout->id,
                stripePlatform: $this->stripeConfigurationService->getPrimaryPlatform()?->value ?? null,
                amountMinor: $payout->amount ?? null,
                currency: $payout->currency ?? null,
                payoutDate: isset($payout->arrival_date) ? (new \DateTimeImmutable())->setTimestamp($payout->arrival_date) : null,
                status: $payout->status,
                metadata: $payout->metadata?->toArray(),
            );

            $this->stripePayoutService->createOrUpdatePayout($dto);
        } catch (Throwable $exception) {
            $this->logger->error('Failed to process payout.paid event', [
                'exception' => $exception->getMessage(),
                'payout_id' => $payout->id ?? null,
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }
}
