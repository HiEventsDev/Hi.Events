<?php

namespace HiEvents\Enterprise\BoxOffice\Resources;

use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeSessionCreatedDTO;
use HiEvents\Resources\EventOccurrence\EventOccurrenceResourcePublic;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BoxOfficeSessionCreatedDTO
 */
class BoxOfficeSessionResourcePublic extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'token' => $this->token,
            'expires_at' => $this->expires_at,
            'operator_name' => $this->operator_name,
            'event_occurrence' => $this->when(
                $this->event_occurrence !== null,
                fn () => new EventOccurrenceResourcePublic($this->event_occurrence),
            ),
            'reader' => $this->when($this->reader !== null, fn () => [
                'id' => $this->reader->id,
                'label' => $this->reader->label,
            ]),
            'check_in_list_short_id' => $this->check_in_list_short_id,
            'check_in_available' => $this->check_in_available,
            'check_in_unavailable_reason' => $this->check_in_unavailable_reason,
        ];
    }
}
