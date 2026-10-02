<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Resources;

use HiEvents\DomainObjects\EventSeatMapDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin EventSeatMapDomainObject
 */
class EventSeatMapResource extends BaseResource
{
    use SerializesSeatMapLayout;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'event_id' => $this->getEventId(),
            'version' => $this->getVersion(),
            'layout' => $this->layoutForResponse($this->getLayout()),
            'source_seat_map' => $this->getSeatMap() === null ? null : [
                'id' => $this->getSeatMap()->getId(),
                'name' => $this->getSeatMap()->getName(),
            ],
            'is_update_available_from_source' => $this->isUpdateAvailableFromSource(),
            'prevent_orphan_seats' => $this->getPreventOrphanSeats(),
            'max_seats_per_order' => $this->getMaxSeatsPerOrder(),
            'allow_seat_change' => $this->getAllowSeatChange(),
            'band_products' => $this->bandProductsForResponse($this->getEventSeatMapBandProducts(), includePriceAdjustments: true),
        ];
    }
}
