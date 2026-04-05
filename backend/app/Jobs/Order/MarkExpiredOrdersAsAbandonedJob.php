<?php

namespace HiEvents\Jobs\Order;

use Carbon\Carbon;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\EventStatisticsVersionMismatchException;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Log\LoggerInterface;
use Throwable;

class MarkExpiredOrdersAsAbandonedJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function handle(
        OrderRepositoryInterface               $orderRepository,
        EventStatisticRepositoryInterface      $eventStatisticsRepository,
        EventDailyStatisticRepositoryInterface $eventDailyStatisticRepository,
        LoggerInterface                        $logger,
    ): void
    {
        // Find all RESERVED orders whose reservation has expired
        $expiredOrders = $orderRepository->findWhere([
            'status' => OrderStatus::RESERVED->name,
        ]);

        $now = Carbon::now();
        $abandonedByEvent = [];

        foreach ($expiredOrders as $order) {
            if ($order->getReservedUntil() === null) {
                continue;
            }

            $reservedUntil = new Carbon($order->getReservedUntil());

            if ($reservedUntil->isFuture()) {
                continue;
            }

            try {
                $orderRepository->updateWhere(
                    attributes: ['status' => OrderStatus::ABANDONED->name],
                    where: [
                        'id' => $order->getId(),
                        'status' => OrderStatus::RESERVED->name,
                    ]
                );

                $eventId = $order->getEventId();
                $abandonedByEvent[$eventId] = ($abandonedByEvent[$eventId] ?? 0) + 1;

                $logger->info('Marked expired order as abandoned', [
                    'order_id' => $order->getId(),
                    'event_id' => $eventId,
                    'reserved_until' => $order->getReservedUntil(),
                ]);
            } catch (Throwable $e) {
                $logger->error('Failed to mark order as abandoned', [
                    'order_id' => $order->getId(),
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        // Update event statistics with abandoned counts
        foreach ($abandonedByEvent as $eventId => $count) {
            try {
                $this->incrementAbandonedStats(
                    $eventId,
                    $count,
                    $eventStatisticsRepository,
                    $eventDailyStatisticRepository,
                );
            } catch (Throwable $e) {
                $logger->error('Failed to update abandoned order stats', [
                    'event_id' => $eventId,
                    'count' => $count,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($abandonedByEvent)) {
            $total = array_sum($abandonedByEvent);
            $logger->info("Marked {$total} expired orders as abandoned across " . count($abandonedByEvent) . ' events');
        }
    }

    private function incrementAbandonedStats(
        int                                    $eventId,
        int                                    $count,
        EventStatisticRepositoryInterface      $eventStatisticsRepository,
        EventDailyStatisticRepositoryInterface $eventDailyStatisticRepository,
    ): void
    {
        // Update aggregate statistics
        $stats = $eventStatisticsRepository->findFirstWhere(['event_id' => $eventId]);
        if ($stats !== null) {
            $eventStatisticsRepository->updateWhere(
                attributes: [
                    'orders_abandoned' => $stats->getOrdersAbandoned() + $count,
                    'version' => $stats->getVersion() + 1,
                ],
                where: [
                    'event_id' => $eventId,
                    'version' => $stats->getVersion(),
                ]
            );
        }

        // Update daily statistics
        $today = Carbon::now()->format('Y-m-d');
        $dailyStats = $eventDailyStatisticRepository->findFirstWhere([
            'event_id' => $eventId,
            'date' => $today,
        ]);

        if ($dailyStats !== null) {
            $eventDailyStatisticRepository->updateWhere(
                attributes: [
                    'orders_abandoned' => $dailyStats->getOrdersAbandoned() + $count,
                    'version' => $dailyStats->getVersion() + 1,
                ],
                where: [
                    'event_id' => $eventId,
                    'date' => $today,
                    'version' => $dailyStats->getVersion(),
                ]
            );
        } else {
            $eventDailyStatisticRepository->create([
                'event_id' => $eventId,
                'date' => $today,
                'orders_abandoned' => $count,
            ]);
        }
    }
}
