<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Announcements;

use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Announcement\AnnouncementResource;
use HiEvents\Services\Application\Handlers\Announcement\DTO\GetActiveAnnouncementsDTO;
use HiEvents\Services\Application\Handlers\Announcement\GetActiveAnnouncementsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class GetActiveAnnouncementsAction extends BaseAction
{
    public function __construct(
        private readonly GetActiveAnnouncementsHandler $handler,
    ) {}

    public function __invoke(): JsonResponse
    {
        if ((bool) Auth::payload()->get('is_impersonating', false)) {
            return $this->jsonResponse(['data' => []]);
        }

        try {
            $accountId = $this->getAuthenticatedAccountId();
        } catch (UnauthorizedException) {
            return $this->jsonResponse(['data' => []]);
        }

        $announcements = $this->handler->handle(new GetActiveAnnouncementsDTO(
            userId: $this->getAuthenticatedUser()->getId(),
            accountId: $accountId,
        ));

        return $this->resourceResponse(
            resource: AnnouncementResource::class,
            data: $announcements,
        );
    }
}
