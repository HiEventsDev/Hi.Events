<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Events;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Event\EventResource;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetUpcomingEventsDTO;
use HiEvents\Services\Application\Handlers\Admin\GetUpcomingEventsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetUpcomingEventsAction extends BaseAction
{
    public function __construct(
        private readonly GetUpcomingEventsHandler $handler,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $events = $this->handler->handle(new GetUpcomingEventsDTO(
            perPage: min((int)$request->query('per_page', 20), 100),
        ));

        return $this->resourceResponse(
            resource: EventResource::class,
            data: $events
        );
    }
}
