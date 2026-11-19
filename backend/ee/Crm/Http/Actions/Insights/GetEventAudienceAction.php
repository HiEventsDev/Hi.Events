<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Insights;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight\GetEventAudienceHandler;
use Illuminate\Http\JsonResponse;

class GetEventAudienceAction extends BaseCrmAction
{
    public function __construct(
        private readonly GetEventAudienceHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $eventId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->jsonResponse($this->handler->handle($organizerId, $eventId), wrapInData: true);
    }
}
