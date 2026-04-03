<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Orders;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Order\AdminOrderDetailResource;
use HiEvents\Services\Application\Handlers\Admin\GetAdminOrderHandler;
use Illuminate\Http\JsonResponse;

class GetAdminOrderAction extends BaseAction
{
    public function __construct(
        private readonly GetAdminOrderHandler $handler,
    )
    {
    }

    public function __invoke(int $orderId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $order = $this->handler->handle($orderId);

        if (!$order) {
            return $this->jsonResponse(['message' => 'Order not found'], ResponseCodes::HTTP_NOT_FOUND);
        }

        return $this->resourceResponse(
            resource: AdminOrderDetailResource::class,
            data: $order
        );
    }
}
