<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Badges\Public;

use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Badge\BadgeResource;
use HiEvents\Services\Application\Handlers\Badge\Public\GetCheckInListAttendeeBadgePublicHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class GetCheckInListAttendeeBadgePublicAction extends BaseAction
{
    public function __construct(
        private readonly GetCheckInListAttendeeBadgePublicHandler $handler,
    ) {}

    public function __invoke(string $checkInListShortId, string $attendeePublicId): JsonResponse
    {
        try {
            $badge = $this->handler->handle($checkInListShortId, $attendeePublicId);
        } catch (CannotCheckInException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: Response::HTTP_FORBIDDEN,
            );
        }

        return $this->resourceResponse(
            resource: BadgeResource::class,
            data: $badge,
        );
    }
}
