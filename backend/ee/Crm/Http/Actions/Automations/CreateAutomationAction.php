<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Automations;

use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;
use HiEvents\Enterprise\Crm\Http\Request\UpsertAutomationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CreateAutomationAction extends BaseUpsertAutomationAction
{
    public function __invoke(UpsertAutomationRequest $request, int $organizerId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        return $this->upsert($request, $organizerId, CrmAutomationTrigger::from($request->validated('trigger')), null, Response::HTTP_CREATED);
    }
}
