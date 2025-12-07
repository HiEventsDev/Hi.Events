<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Orders;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\AdminOrderResource;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAllOrdersDTO;
use HiEvents\Services\Application\Handlers\Admin\GetAllOrdersHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAllOrdersAction extends BaseAction
{
    public function __construct(
        private readonly GetAllOrdersHandler $handler,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $orders = $this->handler->handle(new GetAllOrdersDTO(
            perPage: min((int)$request->query('per_page', 20), 100),
            search: $request->query('search'),
            sortBy: $request->query('sort_by', 'created_at'),
            sortDirection: $request->query('sort_direction', 'desc'),
        ));

        return $this->resourceResponse(
            resource: AdminOrderResource::class,
            data: $orders
        );
    }
}
