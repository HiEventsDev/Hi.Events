<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Events;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Event\AdminEventResource;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAllEventsDTO;
use HiEvents\Services\Application\Handlers\Admin\GetAllEventsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAllEventsAction extends BaseAction
{
    public function __construct(
        private readonly GetAllEventsHandler $handler,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $events = $this->handler->handle(new GetAllEventsDTO(
            perPage: min((int)$request->query('per_page', 20), 100),
            search: $request->query('search'),
            sortBy: $request->query('sort_by', 'start_date'),
            sortDirection: $request->query('sort_direction', 'desc'),
        ));

        return $this->resourceResponse(
            resource: AdminEventResource::class,
            data: $events
        );
    }
}
