<?php

namespace HiEvents\Http\Actions\Events;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsIncrementService;
use Illuminate\Http\JsonResponse;
use Psr\Log\LoggerInterface;
use Throwable;

class TrackExternalRegistrationClickAction extends BaseAction
{
    public function __construct(
        private readonly EventStatisticsIncrementService $eventStatisticsIncrementService,
        private readonly LoggerInterface                 $logger,
    )
    {
    }

    public function __invoke(int $eventId): JsonResponse
    {
        try {
            $this->eventStatisticsIncrementService->incrementExternalRegistrationClick($eventId);

            return $this->successResponse();
        } catch (Throwable $e) {
            // Silent failure - log error but don't block user
            $this->logger->error(
                'Failed to track external registration click',
                [
                    'event_id' => $eventId,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]
            );

            // Still return success to not block the user's navigation
            return $this->successResponse();
        }
    }
}
