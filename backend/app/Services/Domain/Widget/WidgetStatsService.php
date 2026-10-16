<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Widget;

use HiEvents\DomainObjects\WidgetDomainObject;
use HiEvents\Repository\DTO\WidgetStatsDTO;
use HiEvents\Repository\Interfaces\WidgetRepositoryInterface;
use Illuminate\Support\Collection;

class WidgetStatsService
{
    public function __construct(
        private readonly WidgetRepositoryInterface $widgetRepository,
        private readonly WidgetViewIncrementService $widgetViewIncrementService,
    ) {}

    /**
     * @param  Collection<int, WidgetDomainObject>  $widgets
     * @return Collection<int, WidgetDomainObject>
     */
    public function attachStats(Collection $widgets): Collection
    {
        $stats = $this->widgetRepository->getStatsForWidgets(
            $widgets->map(static fn (WidgetDomainObject $widget) => $widget->getId())->values()->all(),
        );

        return $widgets->each(function (WidgetDomainObject $widget) use ($stats) {
            $orderStats = $stats->get($widget->getId()) ?? new WidgetStatsDTO;

            $widget->setStats(new WidgetStatsDTO(
                orders: $orderStats->orders,
                revenue: $orderStats->revenue,
                views: $widget->getViewCount() + $this->widgetViewIncrementService->pendingViews($widget->getId()),
            ));
        });
    }
}
