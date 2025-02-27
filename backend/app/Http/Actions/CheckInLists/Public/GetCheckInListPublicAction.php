<?php

namespace HiEvents\Http\Actions\CheckInLists\Public;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\CheckInList\CheckInListResourcePublic;
use HiEvents\Services\Application\Handlers\CheckInList\Public\GetCheckInListPublicHandler;
use Illuminate\Http\JsonResponse;

class GetCheckInListPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetCheckInListPublicHandler $getCheckInListPublicHandler,
    )
    {
    }

    public function __invoke(string $checkInListShortId): JsonResponse
    {
        $checkInList = $this->getCheckInListPublicHandler->handle($checkInListShortId);

        return $this->resourceResponse(
            resource: CheckInListResourcePublic::class,
            data: $checkInList,
        );
    }
}
