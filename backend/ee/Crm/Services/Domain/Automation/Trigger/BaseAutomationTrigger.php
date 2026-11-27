<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger;

use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\AutomationRunner;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationFailureDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationRunDTO;
use Illuminate\Support\Carbon;

abstract class BaseAutomationTrigger implements AutomationTrigger
{
    public function settled(CrmAutomationDomainObject $automation, AutomationRunDTO $run): void {}

    public function waitingOn(CrmAutomationDomainObject $automation): ?AutomationFailureDTO
    {
        return null;
    }

    /**
     * @return array<string, int|string|null>
     */
    protected function audienceAttributes(CrmAutomationDomainObject $automation): array
    {
        return [
            CrmCampaignDomainObjectAbstract::SEGMENT_ID => $automation->getSegmentId(),
            CrmCampaignDomainObjectAbstract::PRESET_SEGMENT => $automation->getSegmentId() === null ? $automation->getPresetSegment() : null,
        ];
    }

    protected function occurrenceLabel(EventDomainObject $event, string $startDateUtc): string
    {
        return $event->getTitle().' · '.Carbon::parse($startDateUtc, 'UTC')->setTimezone($event->getTimezone() ?? 'UTC')->toDateString();
    }

    protected function runSlot(): string
    {
        $now = now('UTC');
        $interval = AutomationRunner::RUN_INTERVAL_MINUTES;

        return $now->copy()->startOfHour()->addMinutes(intdiv($now->minute, $interval) * $interval)->format('YmdHi');
    }
}
