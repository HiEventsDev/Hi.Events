<?php

namespace HiEvents\Http\Actions\CheckInLists\Public;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\CheckInList\CheckInListStatsPublicResource;
use HiEvents\Services\Application\Handlers\CheckInList\Public\GetCheckInListStatsPublicHandler;
use Illuminate\Http\JsonResponse;

class GetCheckInListStatsPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetCheckInListStatsPublicHandler $getCheckInListStatsPublicHandler,
    )
    {
    }

    public function __invoke(string $checkInListShortId): JsonResponse
    {
        $stats = $this->getCheckInListStatsPublicHandler->handle($checkInListShortId);

        return $this->resourceResponse(
            resource: CheckInListStatsPublicResource::class,
            data: $stats,
        );
    }
}
