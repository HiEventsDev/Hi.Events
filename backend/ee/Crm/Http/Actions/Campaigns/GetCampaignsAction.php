<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Campaigns;

use HiEvents\DomainObjects\Enums\CrmCampaignSource;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\CampaignResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\GetCampaignsHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetCampaignsAction extends BaseCrmAction
{
    private const MAX_SEARCH_LENGTH = 200;

    public function __construct(
        private readonly GetCampaignsHandler $handler,
    ) {}

    public function __invoke(Request $request, int $organizerId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        $folder = $request->query('folder');
        $source = $request->query('source');
        $search = $request->query('query');
        $source = is_string($source) ? CrmCampaignSource::tryFrom($source) : null;
        $search = is_string($search) && trim($search) !== '' ? mb_substr(trim($search), 0, self::MAX_SEARCH_LENGTH) : null;

        return $this->resourceResponse(
            resource: CampaignResource::class,
            data: $this->handler->handle(
                $organizerId,
                is_string($folder) ? $folder : null,
                $source,
                $search,
                $this->getPaginationQueryParams($request),
                $this->canViewFinancials(),
            ),
            meta: [
                'folders' => $this->handler->folderCounts($organizerId),
                'send_gate' => $this->handler->sendGate($this->getAuthenticatedAccountId()),
                'scheduled_send_window_hours' => CampaignLaunchService::SCHEDULED_SEND_WINDOW_HOURS,
            ],
        );
    }
}
