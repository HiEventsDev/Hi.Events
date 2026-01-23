<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAllAccountsDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAllAccountsHandler
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
    )
    {
    }

    public function handle(GetAllAccountsDTO $dto): LengthAwarePaginator
    {
        return $this->accountRepository->getAllAccountsWithCounts(
            search: $dto->search,
            perPage: $dto->perPage,
        );
    }
}
