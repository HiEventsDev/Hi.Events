<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Campaigns;

use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\SendCampaignRequest;
use HiEvents\Enterprise\Crm\Resources\CampaignResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\SendCampaignHandler;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SendCampaignAction extends BaseCrmAction
{
    public function __construct(
        private readonly SendCampaignHandler $handler,
    ) {}

    public function __invoke(SendCampaignRequest $request, int $organizerId, int $campaignId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        try {
            $campaign = $this->handler->handle(
                $organizerId,
                $campaignId,
                $request->validated('scheduled_at'),
                $this->canViewFinancials(),
            );
        } catch (CampaignNotSendableException|UnrecognizedProductIdException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->resourceResponse(CampaignResource::class, $campaign);
    }
}
