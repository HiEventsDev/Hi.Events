<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Automations;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Automation\DeleteAutomationHandler;
use Illuminate\Http\Response;

class DeleteAutomationAction extends BaseCrmAction
{
    public function __construct(
        private readonly DeleteAutomationHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $automationId): Response
    {
        $this->authorizeCrm($organizerId);

        $this->handler->handle($organizerId, $automationId);

        return $this->deletedResponse();
    }
}
