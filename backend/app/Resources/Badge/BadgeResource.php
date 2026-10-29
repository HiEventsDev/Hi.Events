<?php

declare(strict_types=1);

namespace HiEvents\Resources\Badge;

use HiEvents\Services\Domain\Badge\DTO\BadgeDTO;
use HiEvents\Services\Domain\Badge\DTO\BadgeLineDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BadgeDTO
 */
class BadgeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'attendee_id' => $this->attendee_id,
            'attendee_public_id' => $this->attendee_public_id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'event_occurrence_id' => $this->event_occurrence_id,
            /** @var 'ACTIVE'|'AWAITING_PAYMENT'|'CANCELLED' */
            'status' => $this->status,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'lines' => collect($this->lines)->map(fn (BadgeLineDTO $line) => [
                /** @var 'FIRST_NAME'|'LAST_NAME'|'FULL_NAME'|'TICKET_TYPE'|'QUESTION'|'OCCURRENCE_DATE'|'CUSTOM_TEXT' */
                'source' => $line->source->value,
                /** @var 'XL'|'L'|'M'|'S' */
                'size' => $line->size->value,
                'text' => $line->text,
            ])->values()->all(),
            'ribbon' => $this->ribbon === null ? null : [
                'label' => $this->ribbon->label,
                'color' => $this->ribbon->color,
            ],
            'seat_label' => $this->seat_label,
            'badge_printed_at' => $this->badge_printed_at,
            'badge_print_count' => $this->badge_print_count,
            /** @var 'NOT_PRINTED'|'PRINTED'|'NEEDS_REPRINT' */
            'print_status' => $this->print_status->value,
        ];
    }
}
