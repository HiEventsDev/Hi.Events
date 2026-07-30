<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Announcements;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\AnnouncementNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Announcement\DeleteAnnouncementHandler;
use Symfony\Component\HttpFoundation\Response;

class DeleteAnnouncementAction extends BaseAction
{
    public function __construct(
        private readonly DeleteAnnouncementHandler $handler,
    ) {}

    public function __invoke(int $announcementId): Response
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        try {
            $this->handler->handle($announcementId);
        } catch (AnnouncementNotFoundException) {
            return $this->notFoundResponse();
        }

        return $this->deletedResponse();
    }
}
