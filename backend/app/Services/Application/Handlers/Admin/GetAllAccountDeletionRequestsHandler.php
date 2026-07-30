<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Repository\Interfaces\AccountDeletionRequestRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAllAccountDeletionRequestsDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAllAccountDeletionRequestsHandler
{
    public function __construct(
        private readonly AccountDeletionRequestRepositoryInterface $deletionRequestRepository,
    ) {}

    public function handle(GetAllAccountDeletionRequestsDTO $dto): LengthAwarePaginator
    {
        return $this->deletionRequestRepository->getAllRequestsWithAccounts(
            search: $dto->search,
            status: $dto->status,
            perPage: $dto->perPage,
        );
    }
}
