<?php

declare(strict_types=1);

namespace HiEvents\Resources\Widget;

use HiEvents\DomainObjects\WidgetDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin WidgetDomainObject
 */
class WidgetResourcePublic extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'short_id' => $this->getShortId(),
            /** @var 'TICKETS'|'BUTTON'|'LINEUP'|'SCHEDULE'|'EVENTS' */
            'type' => $this->getType(),
            'event_id' => $this->getEventId(),
            'organizer_id' => $this->getOrganizerId(),
            'settings' => (object) $this->getSettings(),
        ];
    }
}
