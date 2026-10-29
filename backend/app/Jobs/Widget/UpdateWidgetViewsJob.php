<?php

namespace HiEvents\Jobs\Widget;

use HiEvents\DomainObjects\Generated\WidgetDomainObjectAbstract;
use HiEvents\Repository\Interfaces\WidgetRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateWidgetViewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $widgetId,
        private readonly int $amount,
    ) {}

    public function handle(WidgetRepositoryInterface $widgetRepository): void
    {
        $widgetRepository->incrementEach(
            columns: [WidgetDomainObjectAbstract::VIEW_COUNT => $this->amount],
            where: [WidgetDomainObjectAbstract::ID => $this->widgetId],
        );
    }
}
