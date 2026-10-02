<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Resources;

use HiEvents\DomainObjects\SeatMapDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin SeatMapDomainObject
 */
class SeatMapSummaryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'organizer_id' => $this->getOrganizerId(),
            'name' => $this->getName(),
            'version' => $this->getVersion(),
            'seat_count' => $this->getSeatCount(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}
