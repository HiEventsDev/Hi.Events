<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Announcements;

use HiEvents\Exceptions\AnnouncementNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Announcement\DismissAnnouncementHandler;
use HiEvents\Services\Application\Handlers\Announcement\DTO\DismissAnnouncementDTO;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DismissAnnouncementAction extends BaseAction
{
    public function __construct(
        private readonly DismissAnnouncementHandler $handler,
    ) {}

    public function __invoke(int $announcementId): Response
    {
        if ((bool) Auth::payload()->get('is_impersonating', false)) {
            return $this->noContentResponse();
        }

        try {
            $this->handler->handle(new DismissAnnouncementDTO(
                announcementId: $announcementId,
                userId: $this->getAuthenticatedUser()->getId(),
            ));
        } catch (AnnouncementNotFoundException) {
            return $this->notFoundResponse();
        }

        return $this->noContentResponse();
    }
}
