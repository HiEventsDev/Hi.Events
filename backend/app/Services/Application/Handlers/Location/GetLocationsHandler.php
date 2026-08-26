<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Location;

use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\LocationRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class GetLocationsHandler
{
    public function __construct(
        private readonly LocationRepositoryInterface $locationRepository,
    ) {}

    public function handle(int $organizerId, int $accountId, QueryParamsDTO $params): LengthAwarePaginator
    {
        return $this->locationRepository->findByOrganizerId($organizerId, $accountId, $params);
    }
}
