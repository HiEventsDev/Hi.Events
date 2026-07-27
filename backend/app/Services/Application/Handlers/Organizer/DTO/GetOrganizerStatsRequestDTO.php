<?php

namespace HiEvents\Services\Application\Handlers\Organizer\DTO;

class GetOrganizerStatsRequestDTO
{
    public function __construct(
        public readonly int $organizerId,
        public readonly int $accountId,
        public ?string $currencyCode = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public string $dateRangePreset = 'month',
    ) {}
}
