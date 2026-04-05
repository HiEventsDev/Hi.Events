<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Events;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Services\Domain\Event\PrivateEventAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValidatePrivateEventAccessAction extends BaseAction
{
    public function __construct(
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
        private readonly PrivateEventAccessService        $privateEventAccessService,
    )
    {
    }

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $request->validate([
            'access_code' => ['required', 'string'],
        ]);

        $settings = $this->eventSettingsRepository->findFirstWhere([
            'event_id' => $eventId,
        ]);

        if (!$settings) {
            return $this->errorResponse(
                message: __('Event not found'),
                statusCode: 404,
            );
        }

        try {
            $this->privateEventAccessService->validateAccess(
                settings: $settings,
                accessCode: $request->input('access_code'),
            );
        } catch (UnauthorizedException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: 403,
            );
        }

        return $this->jsonResponse([
            'access_granted' => true,
        ]);
    }
}
