<?php

declare(strict_types=1);

namespace HiEvents\Resources\Widget;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\WidgetDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin WidgetDomainObject
 */
class WidgetResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $stats = $this->getStats();

        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'name' => $this->getName(),
            /** @var 'TICKETS'|'BUTTON'|'LINEUP'|'SCHEDULE'|'EVENTS' */
            'type' => $this->getType(),
            'event_id' => $this->getEventId(),
            'organizer_id' => $this->getOrganizerId(),
            /** @var array<string, mixed> */
            'settings' => (object) $this->getSettings(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
            'stats' => [
                'views' => $stats->views,
                'orders' => $stats->orders,
                $this->mergeWhen($this->isPermitted(Permission::VIEW_FINANCIALS), fn () => [
                    /** @var array<string, float> */
                    'revenue' => (object) $stats->revenue,
                ]),
            ],
        ];
    }
}
