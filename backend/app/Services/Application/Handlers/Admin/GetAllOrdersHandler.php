<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAllOrdersDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAllOrdersHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    )
    {
    }

    public function handle(GetAllOrdersDTO $dto): LengthAwarePaginator
    {
        return $this->orderRepository->getAllOrdersForAdmin(
            search: $dto->search,
            perPage: $dto->perPage,
            sortBy: $dto->sortBy,
            sortDirection: $dto->sortDirection,
        );
    }
}
