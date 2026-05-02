<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAllOrganizersDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAllOrganizersHandler
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
    ) {}

    public function handle(GetAllOrganizersDTO $dto): LengthAwarePaginator
    {
        return $this->organizerRepository->getAllOrganizersForAdmin(
            search: $dto->search,
            perPage: $dto->perPage,
        );
    }
}
