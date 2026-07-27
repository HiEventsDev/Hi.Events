<?php

namespace HiEvents\Jobs\Event;

use Carbon\Carbon;
use Exception;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Log\LoggerInterface;

class UpdateEventPageViewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $eventId;

    private int $amount;

    public function __construct(int $eventId, int $amount)
    {
        $this->eventId = $eventId;
        $this->amount = $amount;
    }

    public function handle(
        EventStatisticRepositoryInterface $eventStatisticsRepository,
        EventDailyStatisticRepositoryInterface $eventDailyStatisticRepository,
        LoggerInterface $logger,
    ): void {
        try {
            $eventStatisticsRepository->incrementWhere(
                where: ['event_id' => $this->eventId],
                column: 'total_views',
                amount: $this->amount,
            );

            $date = Carbon::now('UTC')->format('Y-m-d');

            $incremented = $eventDailyStatisticRepository->incrementWhere(
                where: ['event_id' => $this->eventId, 'date' => $date],
                column: 'total_views',
                amount: $this->amount,
            );

            if ($incremented === 0) {
                try {
                    $eventDailyStatisticRepository->create([
                        'event_id' => $this->eventId,
                        'date' => $date,
                        'total_views' => $this->amount,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    $eventDailyStatisticRepository->incrementWhere(
                        where: ['event_id' => $this->eventId, 'date' => $date],
                        column: 'total_views',
                        amount: $this->amount,
                    );
                }
            }
        } catch (Exception $e) {
            $logger->error('Failed to update event page views', [
                'event_id' => $this->eventId,
                'amount' => $this->amount,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
