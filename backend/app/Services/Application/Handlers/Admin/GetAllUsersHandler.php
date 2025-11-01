<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAllUsersDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAllUsersHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    )
    {
    }

    public function handle(GetAllUsersDTO $dto): LengthAwarePaginator
    {
        return $this->userRepository->getAllUsersWithAccounts(
            search: $dto->search,
            perPage: $dto->perPage,
        );
    }
}
