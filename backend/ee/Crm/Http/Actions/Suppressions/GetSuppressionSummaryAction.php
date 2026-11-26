<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Suppressions;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\SuppressionSummaryResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Suppression\GetSuppressionSummaryHandler;
use Illuminate\Http\JsonResponse;

class GetSuppressionSummaryAction extends BaseCrmAction
{
    public function __construct(
        private readonly GetSuppressionSummaryHandler $handler,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->resourceResponse(SuppressionSummaryResource::class, $this->handler->handle($organizerId));
    }
}
