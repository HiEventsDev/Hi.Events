<?php

namespace HiEvents\Http\Actions\CheckInLists\Public;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\CheckInList\CheckInListStatsPublicResource;
use HiEvents\Services\Application\Handlers\CheckInList\Public\GetCheckInListStatsPublicHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetCheckInListStatsPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetCheckInListStatsPublicHandler $getCheckInListStatsPublicHandler,
    )
    {
    }

    public function __invoke(string $checkInListShortId, Request $request): JsonResponse
    {
        // Optional filter pill value; handler ignores it for scoped lists.
        $occurrenceId = $request->query('event_occurrence_id');
        $occurrenceIdInt = is_numeric($occurrenceId) ? (int) $occurrenceId : null;

        $stats = $this->getCheckInListStatsPublicHandler->handle($checkInListShortId, $occurrenceIdInt);

        return $this->resourceResponse(
            resource: CheckInListStatsPublicResource::class,
            data: $stats,
        );
    }
}
