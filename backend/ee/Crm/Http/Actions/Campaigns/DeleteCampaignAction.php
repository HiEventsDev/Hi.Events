<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Campaigns;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\DeleteCampaignHandler;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DeleteCampaignAction extends BaseCrmAction
{
    public function __construct(
        private readonly DeleteCampaignHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $campaignId): Response|JsonResponse
    {
        $this->authorizeCrm($organizerId);

        try {
            $this->handler->handle($organizerId, $campaignId);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->deletedResponse();
    }
}
