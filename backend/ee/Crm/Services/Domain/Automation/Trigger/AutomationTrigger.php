<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger;

use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\Enterprise\Crm\Exceptions\AutomationNotRunnableException;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationFailureDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationRunDTO;

interface AutomationTrigger
{
    /**
     * @return AutomationRunDTO[]
     *
     * @throws AutomationNotRunnableException
     */
    public function dueRuns(CrmAutomationDomainObject $automation): array;

    public function settled(CrmAutomationDomainObject $automation, AutomationRunDTO $run): void;

    public function waitingOn(CrmAutomationDomainObject $automation): ?AutomationFailureDTO;
}
