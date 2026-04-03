<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAllAttendeesDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAllAttendeesHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
    )
    {
    }

    public function handle(GetAllAttendeesDTO $dto): LengthAwarePaginator
    {
        return $this->attendeeRepository->getAllAttendeesForAdmin(
            search: $dto->search,
            perPage: $dto->perPage,
            sortBy: $dto->sortBy,
            sortDirection: $dto->sortDirection,
        );
    }
}
