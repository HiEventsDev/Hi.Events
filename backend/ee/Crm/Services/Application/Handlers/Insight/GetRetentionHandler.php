<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight;

use Carbon\CarbonInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmInsightsRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight\DTO\RetentionDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;

class GetRetentionHandler
{
    private const MONTHS = 12;

    private const CACHE_SECONDS = 300;

    public function __construct(
        private readonly CrmInsightsRepositoryInterface $insightsRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly CrmCacheVersion $cacheVersion,
    ) {}

    public function handle(int $organizerId): RetentionDTO
    {
        $timezone = $this->organizerRepository->findById($organizerId)->getTimezone() ?? 'UTC';
        $firstMonth = now()->startOfMonth()->subMonths(self::MONTHS - 1);

        [$cohorts, $maxOffset] = $this->cacheVersion->remember(
            $organizerId,
            'retention:'.$timezone.':'.$firstMonth->toDateString(),
            self::CACHE_SECONDS,
            fn (): array => $this->computeCohorts($organizerId, $timezone, $firstMonth),
        );

        return new RetentionDTO(cohorts: $cohorts, max_offset: $maxOffset);
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function computeCohorts(int $organizerId, string $timezone, CarbonInterface $firstMonth): array
    {
        $rows = $this->insightsRepository->getRetentionCohorts($organizerId, $timezone, $firstMonth);

        $cohorts = [];
        $maxOffset = 0;

        foreach ($rows->groupBy('cohort') as $cohort => $cohortRows) {
            $size = (int) ($cohortRows->firstWhere('month_offset', 0)->contacts ?? 0);
            $months = [];

            foreach ($cohortRows as $row) {
                $offset = (int) $row->month_offset;
                $maxOffset = max($maxOffset, $offset);
                $months[] = [
                    'offset' => $offset,
                    'contacts' => (int) $row->contacts,
                    'percent' => $size === 0 ? 0.0 : round((int) $row->contacts / $size * 100, 1),
                ];
            }

            $cohorts[] = ['cohort' => (string) $cohort, 'size' => $size, 'months' => $months];
        }

        return [$cohorts, $maxOffset];
    }
}
