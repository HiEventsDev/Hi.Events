<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Resources;

use HiEvents\DomainObjects\SeatMapDomainObject;
use Illuminate\Http\Request;

/**
 * @mixin SeatMapDomainObject
 */
class SeatMapResource extends SeatMapSummaryResource
{
    use SerializesSeatMapLayout;

    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'layout' => $this->layoutForResponse($this->getLayout()),
        ];
    }
}
