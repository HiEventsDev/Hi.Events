<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Automations;

use Illuminate\Http\JsonResponse;

class DisableAutomationAction extends BaseSetAutomationEnabledAction
{
    public function __invoke(int $organizerId, int $automationId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->setEnabled($organizerId, $automationId, false);
    }
}
