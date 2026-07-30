<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Announcements;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\AnnouncementNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Announcement\UpsertAnnouncementRequest;
use HiEvents\Resources\Announcement\AdminAnnouncementResource;
use HiEvents\Services\Application\Handlers\Announcement\DTO\UpsertAnnouncementDTO;
use HiEvents\Services\Application\Handlers\Announcement\UpdateAnnouncementHandler;
use Symfony\Component\HttpFoundation\Response;

class UpdateAnnouncementAction extends BaseAction
{
    public function __construct(
        private readonly UpdateAnnouncementHandler $handler,
    ) {}

    public function __invoke(UpsertAnnouncementRequest $request, int $announcementId): Response
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        try {
            $announcement = $this->handler->handle(new UpsertAnnouncementDTO(
                title: $request->validated('title'),
                content: $request->validated('content'),
                status: $request->validated('status'),
                displayType: $request->validated('display_type'),
                targetType: $request->validated('target_type'),
                emoji: $request->validated('emoji'),
                targetAccountIds: $request->validated('target_account_ids'),
                targetUserIds: $request->validated('target_user_ids'),
                ctaLabel: $request->validated('cta_label'),
                ctaUrl: $request->validated('cta_url'),
                announcementId: $announcementId,
            ));
        } catch (AnnouncementNotFoundException) {
            return $this->notFoundResponse();
        }

        return $this->resourceResponse(
            resource: AdminAnnouncementResource::class,
            data: $announcement,
        );
    }
}
