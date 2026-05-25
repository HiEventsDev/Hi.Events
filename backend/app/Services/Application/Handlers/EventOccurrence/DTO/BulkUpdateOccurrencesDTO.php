<?php

namespace HiEvents\Services\Application\Handlers\EventOccurrence\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\BulkOccurrenceAction;
use HiEvents\Services\Domain\EventLocation\EventLocationData;

class BulkUpdateOccurrencesDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $event_id,
        public readonly BulkOccurrenceAction $action,
        public readonly string $timezone,
        public readonly ?int $start_time_shift = null,
        public readonly ?int $end_time_shift = null,
        public readonly ?int $capacity = null,
        public readonly bool $clear_capacity = false,
        public readonly bool $future_only = true,
        public readonly bool $skip_overridden = true,
        public readonly bool $refund_orders = false,
        public readonly ?array $occurrence_ids = null,
        public readonly bool $apply_to_all = false,
        public readonly ?string $label = null,
        public readonly bool $clear_label = false,
        public readonly ?int $duration_minutes = null,
        public readonly ?EventLocationData $event_location = null,
        public readonly bool $clear_event_location = false,
    ) {}
}
