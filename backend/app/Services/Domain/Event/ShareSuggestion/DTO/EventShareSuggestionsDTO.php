<?php

namespace HiEvents\Services\Domain\Event\ShareSuggestion\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\ShareSuggestionAvailability;

class EventShareSuggestionsDTO extends BaseDataObject
{
    /**
     * @param  ShareCaptionDTO[]  $captions
     */
    public function __construct(
        public readonly ShareSuggestionAvailability $availability,
        public readonly array $captions,
        public readonly int $remaining_generations,
    ) {}
}
