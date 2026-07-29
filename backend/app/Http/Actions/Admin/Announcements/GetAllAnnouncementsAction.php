<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Announcements;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Announcement\AdminAnnouncementResource;
use HiEvents\Services\Application\Handlers\Announcement\DTO\GetAllAnnouncementsDTO;
use HiEvents\Services\Application\Handlers\Announcement\GetAllAnnouncementsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAllAnnouncementsAction extends BaseAction
{
    public function __construct(
        private readonly GetAllAnnouncementsHandler $handler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $announcements = $this->handler->handle(new GetAllAnnouncementsDTO(
            perPage: min((int) $request->query('per_page', 20), 100),
            search: $request->query('search'),
        ));

        return $this->resourceResponse(
            resource: AdminAnnouncementResource::class,
            data: $announcements,
        );
    }
}
