<?php

namespace HiEvents\Services\Application\Handlers\Organizer;

use HiEvents\Repository\DTO\Organizer\OrganizerStatsResponseDTO;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\DTO\GetOrganizerStatsRequestDTO;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetOrganizerStatsHandler
{
    public function __construct(private readonly OrganizerRepositoryInterface $repository)
    {
    }

    public function handle(GetOrganizerStatsRequestDTO $statsRequestDTO): OrganizerStatsResponseDTO
    {
        $organizer = $this->repository->findFirstWhere([
            'id' => $statsRequestDTO->organizerId,
            'account_id' => $statsRequestDTO->accountId,
        ]);

        if ($organizer === null) {
            throw new ResourceNotFoundException('Organizer not found');
        }

        return $this->repository->getOrganizerStats(
            organizerId: $statsRequestDTO->organizerId,
            accountId: $statsRequestDTO->accountId,
            currencyCode: $statsRequestDTO->currencyCode ?? $organizer->getCurrency(),
        );
    }
}
