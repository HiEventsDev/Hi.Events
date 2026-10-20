<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\AttributionGroupBy;
use HiEvents\Repository\Interfaces\AccountAttributionRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetUtmAttributionStatsDTO;

class GetUtmAttributionStatsHandler
{
    public function __construct(
        private readonly AccountAttributionRepositoryInterface $attributionRepository,
    ) {}

    public function handle(GetUtmAttributionStatsDTO $dto): array
    {
        $dateFrom = $this->toUtcDateTimeString($dto->date_from);
        $dateTo = $this->toUtcDateTimeString($dto->date_to);

        $stats = $this->attributionRepository->getAttributionStats(
            groupBy: AttributionGroupBy::from($dto->group_by),
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            page: $dto->page,
            perPage: $dto->per_page,
        );

        $summary = $this->attributionRepository->getAttributionSummary(
            dateFrom: $dateFrom,
            dateTo: $dateTo,
        );

        return [
            'data' => $stats,
            'summary' => $summary,
        ];
    }

    private function toUtcDateTimeString(?string $date): ?string
    {
        return $date === null ? null : Carbon::parse($date, 'UTC')->utc()->toDateTimeString();
    }
}
