<?php

namespace HiEvents\Http\Actions\Organizers\Stats;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Organizer\DTO\GetOrganizerStatsRequestDTO;
use HiEvents\Services\Application\Handlers\Organizer\GetOrganizerStatsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetOrganizerStatsAction extends BaseAction
{
    public function __construct(
        private readonly GetOrganizerStatsHandler $getOrganizerStatsHandler,
    )
    {
    }

    public function __invoke(Request $request, int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $organizerStats = $this->getOrganizerStatsHandler->handle(new GetOrganizerStatsRequestDTO(
            organizerId: $organizerId,
            accountId: $this->getAuthenticatedAccountId(),
            currencyCode: $request->get('currency_code'),
        ));

        return $this->jsonResponse(
            data: $organizerStats,
            wrapInData: true,
        );
    }
}
