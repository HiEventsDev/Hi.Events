<?php

namespace HiEvents\Console\Commands;

use Carbon\CarbonImmutable;
use HiEvents\Helper\IdHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedDevDashboardDataCommand extends Command
{
    protected $signature = 'dev:seed-dashboard
        {eventId : Event ID to seed dummy historical data for}
        {--days=60 : Days of historical data to generate}
        {--force : Skip the non-production environment check}';

    protected $description = 'Seed dummy historical orders and daily statistics so the dashboard renders with realistic data. Idempotent — re-running replaces previously seeded rows for the event.';

    private const SEED_NOTE = '[dev-seed]';

    private const FIRST_NAMES = [
        'Sarah', 'Liam', 'Aoife', 'Noah', 'Mia', 'Oisin', 'Emma', 'Conor',
        'Sophie', 'Sean', 'Ella', 'Cian', 'Ava', 'Patrick', 'Saoirse', 'Diarmuid',
        'Niamh', 'Eoin', 'Caoimhe', 'Ciaran',
    ];

    private const LAST_NAMES = [
        'O\'Connor', 'Murphy', 'Kelly', 'Byrne', 'Walsh', 'O\'Brien', 'Ryan',
        'O\'Sullivan', 'Doyle', 'Kennedy', 'Lynch', 'Quinn', 'McCarthy', 'Brady',
        'Reilly',
    ];

    public function handle(): int
    {
        if (app()->environment('production') && !$this->option('force')) {
            $this->error('Refusing to run in production. Pass --force to override.');
            return self::FAILURE;
        }

        $eventId = (int)$this->argument('eventId');
        $days = (int)$this->option('days');

        $event = DB::table('events')->where('id', $eventId)->first();
        if ($event === null) {
            $this->error("Event {$eventId} not found.");
            return self::FAILURE;
        }

        $occurrence = DB::table('event_occurrences')
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->first();

        if ($occurrence === null) {
            $this->error("Event {$eventId} has no event_occurrence rows. Cannot seed daily statistics.");
            return self::FAILURE;
        }

        $this->info("Seeding {$days} days of dummy data for event {$eventId} ({$event->title}, {$event->currency})");

        DB::transaction(function () use ($eventId, $days, $event, $occurrence) {
            $this->cleanup($eventId);

            $today = CarbonImmutable::today();
            $aggregateGross = 0.0;
            $aggregateTax = 0.0;
            $aggregateFee = 0.0;
            $aggregateRefunded = 0.0;
            $aggregateOrders = 0;
            $aggregateProducts = 0;
            $aggregateAttendees = 0;
            $aggregateViews = 0;
            $aggregateCancelled = 0;

            $bar = $this->output->createProgressBar($days);
            $bar->start();

            for ($i = $days - 1; $i >= 0; $i--) {
                $date = $today->subDays($i);

                $isWeekend = in_array($date->dayOfWeek, [0, 6], true);
                $orderCount = $this->randomOrderCount($i, $isWeekend);

                $dayProducts = 0;
                $dayAttendees = 0;
                $dayGross = 0.0;
                $dayTax = 0.0;
                $dayFee = 0.0;
                $dayRefunded = 0.0;
                $dayOrdersCreated = 0;
                $dayOrdersCancelled = 0;
                $dayViews = random_int(20, 180) + ($isWeekend ? 50 : 0);

                for ($n = 0; $n < $orderCount; $n++) {
                    $items = random_int(1, 4);
                    $unitPrice = $this->randomChoice([15.00, 25.00, 35.00, 45.00, 75.00]);
                    $beforeAdditions = round($unitPrice * $items, 2);
                    $tax = round($beforeAdditions * 0.135, 2);
                    $fee = round($beforeAdditions * 0.025, 2);
                    $gross = round($beforeAdditions + $tax + $fee, 2);

                    $isCancelled = random_int(1, 100) <= 8;
                    $refundedAmount = 0.0;
                    if (!$isCancelled && random_int(1, 100) <= 6) {
                        $refundedAmount = $gross;
                    }

                    $createdAt = $date->setTime(random_int(8, 22), random_int(0, 59), random_int(0, 59));

                    $status = $isCancelled ? 'CANCELLED' : 'COMPLETED';
                    $paymentStatus = $isCancelled ? null : 'PAYMENT_RECEIVED';
                    $refundStatus = $refundedAmount > 0 ? 'REFUNDED' : null;

                    DB::table('orders')->insert([
                        'short_id' => IdHelper::shortId(IdHelper::ORDER_PREFIX),
                        'public_id' => IdHelper::publicId(IdHelper::ORDER_PREFIX),
                        'event_id' => $eventId,
                        'currency' => $event->currency,
                        'first_name' => $this->randomChoice(self::FIRST_NAMES),
                        'last_name' => $this->randomChoice(self::LAST_NAMES),
                        'email' => 'seed' . random_int(1000, 9999) . '@example.com',
                        'status' => $status,
                        'payment_status' => $paymentStatus,
                        'refund_status' => $refundStatus,
                        'total_before_additions' => $beforeAdditions,
                        'total_gross' => $gross,
                        'total_tax' => $tax,
                        'total_fee' => $fee,
                        'total_refunded' => $refundedAmount,
                        'is_manually_created' => false,
                        'notes' => self::SEED_NOTE,
                        'locale' => 'en',
                        'payment_provider' => 'STRIPE',
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);

                    if ($isCancelled) {
                        $dayOrdersCancelled++;
                        continue;
                    }

                    $dayOrdersCreated++;
                    $dayProducts += $items;
                    $dayAttendees += $items;
                    $dayGross += $gross;
                    $dayTax += $tax;
                    $dayFee += $fee;
                    $dayRefunded += $refundedAmount;
                }

                DB::table('event_occurrence_daily_statistics')->upsert(
                    [
                        [
                            'event_id' => $eventId,
                            'event_occurrence_id' => $occurrence->id,
                            'date' => $date->toDateString(),
                            'products_sold' => $dayProducts,
                            'attendees_registered' => $dayAttendees,
                            'sales_total_gross' => $dayGross,
                            'sales_total_before_additions' => round($dayGross - $dayTax - $dayFee, 2),
                            'total_tax' => $dayTax,
                            'total_fee' => $dayFee,
                            'orders_created' => $dayOrdersCreated,
                            'orders_cancelled' => $dayOrdersCancelled,
                            'total_refunded' => $dayRefunded,
                            'version' => 0,
                            'created_at' => $date,
                            'updated_at' => $date,
                        ],
                    ],
                    ['event_occurrence_id', 'date'],
                    [
                        'products_sold', 'attendees_registered',
                        'sales_total_gross', 'sales_total_before_additions',
                        'total_tax', 'total_fee',
                        'orders_created', 'orders_cancelled', 'total_refunded',
                        'updated_at',
                    ],
                );

                $aggregateGross += $dayGross;
                $aggregateTax += $dayTax;
                $aggregateFee += $dayFee;
                $aggregateRefunded += $dayRefunded;
                $aggregateOrders += $dayOrdersCreated;
                $aggregateProducts += $dayProducts;
                $aggregateAttendees += $dayAttendees;
                $aggregateViews += $dayViews;
                $aggregateCancelled += $dayOrdersCancelled;

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            DB::table('event_statistics')
                ->where('event_id', $eventId)
                ->update([
                    'sales_total_gross' => $aggregateGross,
                    'sales_total_before_additions' => round($aggregateGross - $aggregateTax - $aggregateFee, 2),
                    'total_tax' => $aggregateTax,
                    'total_fee' => $aggregateFee,
                    'total_refunded' => $aggregateRefunded,
                    'orders_created' => $aggregateOrders,
                    'orders_cancelled' => $aggregateCancelled,
                    'products_sold' => $aggregateProducts,
                    'attendees_registered' => $aggregateAttendees,
                    'total_views' => $aggregateViews,
                    'unique_views' => (int)round($aggregateViews * 0.65),
                    'updated_at' => now(),
                ]);

            $this->table(
                ['Metric', 'Total over period'],
                [
                    ['Orders (completed)', $aggregateOrders],
                    ['Orders (cancelled)', $aggregateCancelled],
                    ['Products sold', $aggregateProducts],
                    ['Attendees', $aggregateAttendees],
                    ['Gross sales', number_format($aggregateGross, 2) . ' ' . $event->currency],
                    ['Tax', number_format($aggregateTax, 2) . ' ' . $event->currency],
                    ['Fees', number_format($aggregateFee, 2) . ' ' . $event->currency],
                    ['Refunded', number_format($aggregateRefunded, 2) . ' ' . $event->currency],
                    ['Page views', $aggregateViews],
                ],
            );
        });

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function cleanup(int $eventId): void
    {
        $deletedOrders = DB::table('orders')
            ->where('event_id', $eventId)
            ->where('notes', self::SEED_NOTE)
            ->delete();

        $this->line("Cleaned up {$deletedOrders} prior seed orders. Daily statistics will be upserted for the seeded window.");
    }

    private function randomOrderCount(int $daysAgo, bool $isWeekend): int
    {
        $base = $isWeekend ? random_int(4, 12) : random_int(1, 7);

        if ($daysAgo > 30) {
            $base = (int)round($base * 0.6);
        }
        if (random_int(1, 100) <= 4) {
            $base += random_int(8, 20);
        }
        return max(0, $base);
    }

    private function randomChoice(array $items)
    {
        return $items[array_rand($items)];
    }
}
