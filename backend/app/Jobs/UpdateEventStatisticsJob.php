<?php

namespace HiEvents\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Exceptions\EventStatisticsVersionMismatchException;
use HiEvents\Service\Common\EventStatistics\EventStatisticsUpdateService;

class UpdateEventStatisticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected OrderDomainObject $order;

    public int $tries = 5;

    public int $backoff = 10; // seconds

    public function __construct(OrderDomainObject $order)
    {
        $this->order = $order;
    }

    /**
     * @throws EventStatisticsVersionMismatchException|Throwable
     */
    public function handle(EventStatisticsUpdateService $service): void
    {
        $service->updateStatistics($this->order);
    }

    public function failed(Throwable $exception): void
    {
        logger()?->error('Failed to update event statistics', [
            'order' => $this->order->toArray(),
            'exception' => $exception,
        ]);
    }
}
