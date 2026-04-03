<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Attendees;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Attendee\AdminAttendeeResource;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAllAttendeesDTO;
use HiEvents\Services\Application\Handlers\Admin\GetAllAttendeesHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAllAttendeesAction extends BaseAction
{
    public function __construct(
        private readonly GetAllAttendeesHandler $handler,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $attendees = $this->handler->handle(new GetAllAttendeesDTO(
            perPage: min((int)$request->query('per_page', 20), 100),
            search: $request->query('search'),
            sortBy: $request->query('sort_by', 'created_at'),
            sortDirection: $request->query('sort_direction', 'desc'),
        ));

        return $this->resourceResponse(
            resource: AdminAttendeeResource::class,
            data: $attendees
        );
    }
}
