<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Automations;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\AutomationDefaults;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationTemplateDTO;
use Illuminate\Http\JsonResponse;

class GetAutomationTemplatesAction extends BaseCrmAction
{
    public function __construct(
        private readonly AutomationDefaults $defaults,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->jsonResponse(
            array_map(static fn (AutomationTemplateDTO $template) => $template->toArray(), $this->defaults->templates()),
            wrapInData: true,
        );
    }
}
