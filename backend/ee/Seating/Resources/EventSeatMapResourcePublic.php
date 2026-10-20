<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Resources;

use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\PublicEventSeatMapDTO;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin PublicEventSeatMapDTO
 */
class EventSeatMapResourcePublic extends BaseResource
{
    use SerializesSeatMapLayout;

    public function toArray(Request $request): array
    {
        $eventSeatMap = $this->eventSeatMap;

        return [
            'version' => $eventSeatMap->getVersion(),
            'layout' => $this->layoutForResponse($eventSeatMap->getLayout()),
            'band_products' => $this->bandProductsForResponse($eventSeatMap->getEventSeatMapBandProducts(), includePriceAdjustments: false),
            'prevent_orphan_seats' => $eventSeatMap->getPreventOrphanSeats(),
            'max_seats_per_order' => $eventSeatMap->getMaxSeatsPerOrder(),
            'allow_seat_change' => $eventSeatMap->getAllowSeatChange(),
        ];
    }
}
