<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Insights;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight\GetAudienceOverviewHandler;
use Illuminate\Http\JsonResponse;

class GetAudienceOverviewAction extends BaseCrmAction
{
    public function __construct(
        private readonly GetAudienceOverviewHandler $handler,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->jsonResponse($this->handler->handle($organizerId, $this->canViewFinancials()), wrapInData: true);
    }
}
