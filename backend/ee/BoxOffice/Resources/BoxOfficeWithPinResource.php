<?php

namespace HiEvents\Enterprise\BoxOffice\Resources;

use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeWithPinDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BoxOfficeWithPinDTO
 */
class BoxOfficeWithPinResource extends JsonResource
{
    public function toArray($request): array
    {
        return array_merge(
            (new BoxOfficeResource($this->box_office))->toArray($request),
            ['pin' => $this->pin],
        );
    }
}
