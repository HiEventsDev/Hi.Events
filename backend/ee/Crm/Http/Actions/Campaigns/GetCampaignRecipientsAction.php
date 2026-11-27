<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Campaigns;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\CampaignRecipientResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\GetCampaignRecipientsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetCampaignRecipientsAction extends BaseCrmAction
{
    public function __construct(
        private readonly GetCampaignRecipientsHandler $handler,
    ) {}

    public function __invoke(Request $request, int $organizerId, int $campaignId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        $filter = $request->query('filter');

        return $this->resourceResponse(CampaignRecipientResource::class, $this->handler->handle(
            $organizerId,
            $campaignId,
            is_string($filter) ? $filter : null,
            max((int) $request->query('page', 1), 1),
            $this->canViewFinancials(),
        ));
    }
}
