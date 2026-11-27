<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Automations;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\AutomationResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Automation\GetAutomationsHandler;
use Illuminate\Http\JsonResponse;

class GetAutomationsAction extends BaseCrmAction
{
    public function __construct(
        private readonly GetAutomationsHandler $handler,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->resourceResponse(
            AutomationResource::class,
            $this->handler->handle($organizerId),
        );
    }
}
