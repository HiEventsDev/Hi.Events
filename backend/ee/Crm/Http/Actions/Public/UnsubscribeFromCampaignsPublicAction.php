<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Public;

use HiEvents\Enterprise\Crm\Resources\UnsubscribeStatusResource;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignUnsubscribeService;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;

class UnsubscribeFromCampaignsPublicAction extends BaseAction
{
    public function __construct(
        private readonly CampaignUnsubscribeService $unsubscribeService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(string $token): JsonResponse
    {
        return $this->resourceResponse(UnsubscribeStatusResource::class, $this->unsubscribeService->unsubscribe($token));
    }
}
