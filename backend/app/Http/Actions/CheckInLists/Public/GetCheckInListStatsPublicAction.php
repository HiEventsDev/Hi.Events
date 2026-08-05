<?php

namespace HiEvents\Http\Actions\CheckInLists\Public;

use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\CheckInList\CheckInListStatsPublicResource;
use HiEvents\Services\Application\Handlers\CheckInList\Public\GetCheckInListStatsPublicHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GetCheckInListStatsPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetCheckInListStatsPublicHandler $getCheckInListStatsPublicHandler,
    ) {}

    public function __invoke(string $checkInListShortId, Request $request): JsonResponse
    {
        $occurrenceId = $request->query('event_occurrence_id');
        $occurrenceIdInt = is_numeric($occurrenceId) ? (int) $occurrenceId : null;

        try {
            $stats = $this->getCheckInListStatsPublicHandler->handle($checkInListShortId, $occurrenceIdInt);
        } catch (CannotCheckInException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: Response::HTTP_FORBIDDEN,
            );
        }

        return $this->resourceResponse(
            resource: CheckInListStatsPublicResource::class,
            data: $stats,
        );
    }
}
