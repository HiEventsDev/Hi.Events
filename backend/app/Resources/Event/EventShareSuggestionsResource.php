<?php

namespace HiEvents\Resources\Event;

use HiEvents\Resources\BaseResource;
use HiEvents\Services\Domain\Event\ShareSuggestion\DTO\EventShareSuggestionsDTO;
use HiEvents\Services\Domain\Event\ShareSuggestion\DTO\ShareCaptionDTO;

/**
 * @mixin EventShareSuggestionsDTO
 */
class EventShareSuggestionsResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            /** @var 'AVAILABLE'|'NOT_CONFIGURED'|'EMAIL_NOT_CONFIRMED'|'PAYMENTS_NOT_CONNECTED'|'EVENT_UNDER_REVIEW' */
            'availability' => $this->availability->value,
            /** @var array<int, array{tone: 'FRIENDLY'|'PROFESSIONAL'|'ENERGETIC'|'SHORT', text: string}> */
            'captions' => array_map(
                static fn (ShareCaptionDTO $caption): array => ['tone' => $caption->tone->value, 'text' => $caption->text],
                $this->captions,
            ),
            'remaining_generations' => $this->remaining_generations,
        ];
    }
}
