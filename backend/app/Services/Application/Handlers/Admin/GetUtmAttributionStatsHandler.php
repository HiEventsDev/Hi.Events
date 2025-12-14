<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Repository\Interfaces\AccountAttributionRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetUtmAttributionStatsDTO;

class GetUtmAttributionStatsHandler
{
    public function __construct(
        private readonly AccountAttributionRepositoryInterface $attributionRepository,
    )
    {
    }

    public function handle(GetUtmAttributionStatsDTO $dto): array
    {
        $stats = $this->attributionRepository->getAttributionStats(
            groupBy: $dto->group_by,
            dateFrom: $dto->date_from,
            dateTo: $dto->date_to,
            page: $dto->page,
            perPage: $dto->per_page,
        );

        $summary = $this->attributionRepository->getAttributionSummary(
            dateFrom: $dto->date_from,
            dateTo: $dto->date_to,
        );

        return [
            'data' => $stats,
            'summary' => $summary,
        ];
    }
}
