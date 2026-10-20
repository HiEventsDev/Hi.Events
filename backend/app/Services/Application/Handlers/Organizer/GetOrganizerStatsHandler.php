<?php

namespace HiEvents\Services\Application\Handlers\Organizer;

use Carbon\Carbon;
use HiEvents\Repository\DTO\Organizer\OrganizerStatsResponseDTO;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\DTO\GetOrganizerStatsRequestDTO;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetOrganizerStatsHandler
{
    public function __construct(private readonly OrganizerRepositoryInterface $repository) {}

    public function handle(GetOrganizerStatsRequestDTO $statsRequestDTO): OrganizerStatsResponseDTO
    {
        $organizer = $this->repository->findFirstWhere([
            'id' => $statsRequestDTO->organizerId,
            'account_id' => $statsRequestDTO->accountId,
        ]);

        if ($organizer === null) {
            throw new ResourceNotFoundException('Organizer not found');
        }

        [$startDate, $endDate] = $this->resolveDateRange(
            $statsRequestDTO->startDate,
            $statsRequestDTO->endDate,
            $statsRequestDTO->dateRangePreset,
        );

        return $this->repository->getOrganizerStats(
            organizerId: $statsRequestDTO->organizerId,
            accountId: $statsRequestDTO->accountId,
            currencyCode: $statsRequestDTO->currencyCode ?? $organizer->getCurrency(),
            startDate: $startDate,
            endDate: $endDate,
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(?string $startDate, ?string $endDate, string $preset): array
    {
        if ($startDate !== null && $endDate !== null) {
            return [$startDate, $endDate];
        }

        $end = Carbon::now();

        $start = match ($preset) {
            'week' => (clone $end)->subDays(7),
            'quarter' => (clone $end)->subDays(90),
            'year' => (clone $end)->subDays(365),
            default => (clone $end)->subDays(30),
        };

        return [
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
        ];
    }
}
