<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Campaigns;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\CampaignResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\GetCampaignHandler;
use Illuminate\Http\JsonResponse;

class GetCampaignAction extends BaseCrmAction
{
    public function __construct(
        private readonly GetCampaignHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $campaignId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->resourceResponse(
            CampaignResource::class,
            $this->handler->handle($organizerId, $campaignId, $this->canViewFinancials()),
        );
    }
}
