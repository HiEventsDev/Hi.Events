<?php

namespace HiEvents\Console\Commands;

use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderPaymentPlatformFeeRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentPlatformFeeExtractionService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Illuminate\Console\Command;
use Throwable;

class BackfillPlatformFeesCommand extends Command
{
    protected $signature = 'stripe:backfill-platform-fees
                            {--payout-id= : Only backfill for specific payout ID}
                            {--limit=100 : Maximum number of payments to process}
                            {--dry-run : Show what would be done without actually doing it}';

    protected $description = 'Backfill missing order_payment_platform_fees records from Stripe API';

    public function __construct(
        private readonly StripePaymentsRepository $stripePaymentsRepository,
        private readonly OrderPaymentPlatformFeeRepositoryInterface $orderPaymentPlatformFeeRepository,
        private readonly StripePaymentPlatformFeeExtractionService $platformFeeExtractionService,
        private readonly StripeClientFactory $stripeClientFactory,
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting platform fees backfill...');

        $payoutId = $this->option('payout-id');
        $limit = (int)$this->option('limit');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Find stripe_payments that have payout_id
        $where = [];

        if ($payoutId) {
            $where[StripePaymentDomainObjectAbstract::PAYOUT_ID] = $payoutId;
            $this->info("Filtering by payout ID: {$payoutId}");
        }

        // Get all payments (or filtered by payout_id)
        $allPayments = $this->stripePaymentsRepository
            ->loadRelation(new Relationship(OrderDomainObject::class, name: 'order'))
            ->findWhere($payoutId ? $where : []);

        // Filter to only those without platform fees and with charge_id
        $stripePayments = $allPayments->filter(function ($payment) {
            /** @var StripePaymentDomainObject $payment */

            // Must have charge_id and payout_id
            if (!$payment->getChargeId() || !$payment->getPayoutId()) {
                return false;
            }

            $order = $payment->getOrder();
            if (!$order) {
                return false;
            }

            // Check if platform fee already exists for this order using count
            $existsCount = $this->orderPaymentPlatformFeeRepository->countWhere([
                'order_id' => $order->getId(),
            ]);

            return $existsCount === 0;
        })->take($limit);

        if ($stripePayments->isEmpty()) {
            $this->info('No stripe payments found that need platform fee backfill.');
            return self::SUCCESS;
        }

        $this->info("Found {$stripePayments->count()} payments to process");

        $progressBar = $this->output->createProgressBar($stripePayments->count());
        $progressBar->start();

        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        foreach ($stripePayments as $stripePayment) {
            /** @var StripePaymentDomainObject $stripePayment */
            $order = $stripePayment->getOrder();

            if (!$order) {
                $this->newLine();
                $this->warn("Order not found for stripe_payment ID: {$stripePayment->getId()}");
                $skippedCount++;
                $progressBar->advance();
                continue;
            }

            try {
                if (!$dryRun) {
                    // Fetch charge from Stripe with expanded balance_transaction
                    $stripeClient = $this->stripeClientFactory->createForPlatform(
                        $stripePayment->getStripePlatformEnum()
                    );

                    $params = ['expand' => ['balance_transaction']];
                    $opts = [];

                    if ($stripePayment->getConnectedAccountId()) {
                        $opts['stripe_account'] = $stripePayment->getConnectedAccountId();
                    }

                    $charge = $stripeClient->charges->retrieve(
                        $stripePayment->getChargeId(),
                        $params,
                        $opts
                    );

                    $this->platformFeeExtractionService->extractAndStorePlatformFee(
                        order: $order,
                        charge: $charge,
                        stripePayment: $stripePayment
                    );

                } else {
                    $this->newLine();
                    $this->line("Would process: Order #{$order->getId()}, Charge: {$stripePayment->getChargeId()}");
                }
                $successCount++;
            } catch (Throwable $exception) {
                $this->newLine();
                $this->error("Failed to process order #{$order->getId()}: {$exception->getMessage()}");
                $errorCount++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('Backfill complete!');
        $this->table(
            ['Status', 'Count'],
            [
                ['Success', $successCount],
                ['Errors', $errorCount],
                ['Skipped', $skippedCount],
                ['Total', $stripePayments->count()],
            ]
        );

        return $errorCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
