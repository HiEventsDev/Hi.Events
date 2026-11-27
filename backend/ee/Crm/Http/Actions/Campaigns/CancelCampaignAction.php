<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Campaigns;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\CampaignResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\CancelCampaignHandler;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CancelCampaignAction extends BaseCrmAction
{
    public function __construct(
        private readonly CancelCampaignHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $campaignId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        try {
            $campaign = $this->handler->handle($organizerId, $campaignId, $this->canViewFinancials());
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(CampaignResource::class, $campaign);
    }
}
