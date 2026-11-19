<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight;

use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmInsightsRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight\DTO\LeadTimeDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;

class GetLeadTimeHandler
{
    private const BUCKET_COUNT = 7;

    private const CACHE_SECONDS = 300;

    public function __construct(
        private readonly CrmInsightsRepositoryInterface $insightsRepository,
        private readonly CrmCacheVersion $cacheVersion,
    ) {}

    public function handle(int $organizerId): LeadTimeDTO
    {
        [$buckets, $total] = $this->cacheVersion->remember(
            $organizerId,
            'lead-time:all',
            self::CACHE_SECONDS,
            fn (): array => $this->computeBuckets($organizerId),
        );

        return new LeadTimeDTO(buckets: $buckets, tickets_count: $total);
    }

    /**
     * @return array{0: array<int, array{bucket: int, tickets: int, percent: float}>, 1: int}
     */
    private function computeBuckets(int $organizerId): array
    {
        $counts = $this->insightsRepository->getLeadTimeBuckets($organizerId)->pluck('tickets', 'bucket');
        $total = (int) $counts->sum();

        $buckets = [];
        for ($bucket = 0; $bucket < self::BUCKET_COUNT; $bucket++) {
            $tickets = (int) ($counts[$bucket] ?? 0);
            $buckets[] = [
                'bucket' => $bucket,
                'tickets' => $tickets,
                'percent' => $total === 0 ? 0.0 : round($tickets / $total * 100, 1),
            ];
        }

        return [$buckets, $total];
    }
}
