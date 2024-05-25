<?php

namespace HiEvents\Jobs\Event;

use Exception;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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
        LoggerInterface                   $logger,
    ): void
    {
        try {
            $eventStatisticsRepository->incrementWhere(
                where: ['event_id' => $this->eventId],
                column: 'total_views',
                amount: $this->amount,
            );
        } catch (Exception $e) {
            $logger->error('Failed to update event page views', [
                'event_id' => $this->eventId,
                'amount' => $this->amount,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
