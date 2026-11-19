<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Campaigns;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\GetCampaignSendCheckHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetCampaignSendCheckAction extends BaseCrmAction
{
    public function __construct(
        private readonly GetCampaignSendCheckHandler $handler,
    ) {}

    public function __invoke(Request $request, int $organizerId, int $campaignId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->jsonResponse(
            $this->handler->handle($organizerId, $campaignId, $this->canViewFinancials(), $request->boolean('scheduled'))->toArray(),
            wrapInData: true,
        );
    }
}
