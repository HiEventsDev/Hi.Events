<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\Generated\WidgetDomainObjectAbstract;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\WidgetDomainObject;
use HiEvents\Models\Widget;
use HiEvents\Repository\DTO\WidgetStatsDTO;
use HiEvents\Repository\Interfaces\WidgetRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<WidgetDomainObject>
 */
class WidgetRepository extends BaseRepository implements WidgetRepositoryInterface
{
    protected function getModel(): string
    {
        return Widget::class;
    }

    public function getDomainObject(): string
    {
        return WidgetDomainObject::class;
    }

    public function findForOrderAttribution(string $shortId, int $eventId, int $organizerId): ?WidgetDomainObject
    {
        return $this->findFirstWhere([
            WidgetDomainObjectAbstract::SHORT_ID => $shortId,
            static function (Builder $builder) use ($eventId, $organizerId): void {
                $builder
                    ->where(WidgetDomainObjectAbstract::EVENT_ID, $eventId)
                    ->orWhere(static function (Builder $organizerWidget) use ($organizerId): void {
                        $organizerWidget
                            ->whereNull(WidgetDomainObjectAbstract::EVENT_ID)
                            ->where(WidgetDomainObjectAbstract::ORGANIZER_ID, $organizerId);
                    });
            },
        ]);
    }

    public function getStatsForWidgets(array $widgetIds): Collection
    {
        if ($widgetIds === []) {
            return collect();
        }

        return $this->runQuery(fn () => $this->db->table('orders')
            ->whereIn('widget_id', $widgetIds)
            ->where('status', OrderStatus::COMPLETED->name)
            ->whereNull('deleted_at')
            ->groupBy('widget_id', 'currency')
            ->orderBy('currency')
            ->selectRaw('widget_id, currency, COUNT(*) AS order_count, COALESCE(SUM(total_gross - total_refunded), 0) AS revenue')
            ->get()
            ->groupBy('widget_id')
            ->map(static fn (Collection $rows) => new WidgetStatsDTO(
                orders: (int) $rows->sum('order_count'),
                revenue: $rows->mapWithKeys(static fn ($row) => [$row->currency => round((float) $row->revenue, 2)])->all(),
            )));
    }
}
