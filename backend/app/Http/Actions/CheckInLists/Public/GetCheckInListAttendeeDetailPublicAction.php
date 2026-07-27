<?php

namespace HiEvents\Http\Actions\CheckInLists\Public;

use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Attendee\AttendeeDetailPublicResource;
use HiEvents\Services\Application\Handlers\CheckInList\Public\GetCheckInListAttendeeDetailPublicHandler;
use HiEvents\Services\Domain\Auth\AuthUserService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GetCheckInListAttendeeDetailPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetCheckInListAttendeeDetailPublicHandler $handler,
        private readonly AuthUserService $authUserService,
    ) {}

    public function __invoke(string $checkInListShortId, string $attendeePublicId): JsonResponse
    {
        try {
            $detail = $this->handler->handle(
                shortId: $checkInListShortId,
                attendeePublicId: $attendeePublicId,
                staffAccountId: $this->resolveStaffAccountId(),
            );
        } catch (CannotCheckInException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: Response::HTTP_FORBIDDEN,
            );
        }

        return $this->resourceResponse(
            resource: AttendeeDetailPublicResource::class,
            data: $detail,
        );
    }

    private function resolveStaffAccountId(): ?int
    {
        try {
            return $this->authUserService->getAuthenticatedAccountId();
        } catch (Throwable) {
            return null;
        }
    }
}
