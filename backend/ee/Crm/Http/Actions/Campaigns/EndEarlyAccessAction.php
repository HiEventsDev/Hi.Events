<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Campaigns;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\CampaignResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\EndEarlyAccessHandler;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EndEarlyAccessAction extends BaseCrmAction
{
    public function __construct(
        private readonly EndEarlyAccessHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $campaignId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        try {
            $campaign = $this->handler->handle($organizerId, $campaignId, $this->canViewFinancials());
        } catch (ResourceNotFoundException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(CampaignResource::class, $campaign);
    }
}
