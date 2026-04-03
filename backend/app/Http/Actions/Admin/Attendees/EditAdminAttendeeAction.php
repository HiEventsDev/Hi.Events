<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Attendees;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Attendee\AdminAttendeeResource;
use HiEvents\Services\Application\Handlers\Admin\DTO\EditAdminAttendeeDTO;
use HiEvents\Services\Application\Handlers\Admin\EditAdminAttendeeHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EditAdminAttendeeAction extends BaseAction
{
    public function __construct(
        private readonly EditAdminAttendeeHandler $handler,
    )
    {
    }

    public function __invoke(Request $request, int $attendeeId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $this->validate($request, [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'notes' => 'sometimes|nullable|string|max:5000',
        ]);

        $attendee = $this->handler->handle(new EditAdminAttendeeDTO(
            attendeeId: $attendeeId,
            firstName: $request->input('first_name'),
            lastName: $request->input('last_name'),
            email: $request->input('email'),
            notes: $request->input('notes'),
        ));

        return $this->resourceResponse(
            resource: AdminAttendeeResource::class,
            data: $attendee
        );
    }
}
