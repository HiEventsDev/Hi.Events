<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Campaigns;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\CampaignResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\DuplicateCampaignHandler;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DuplicateCampaignAction extends BaseCrmAction
{
    public function __construct(
        private readonly DuplicateCampaignHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $campaignId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        try {
            $result = $this->handler->handle($organizerId, $campaignId, $this->getAuthenticatedUser()->getId(), $this->canViewFinancials());
        } catch (ResourceNotFoundException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return $this->jsonResponse([
            'data' => new CampaignResource($result->campaign),
            'warnings' => $result->warnings,
        ], Response::HTTP_CREATED);
    }
}
