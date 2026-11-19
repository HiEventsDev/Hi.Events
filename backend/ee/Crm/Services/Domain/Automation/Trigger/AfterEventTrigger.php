<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger;

use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\AutomationDefaults;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationRunDTO;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;

class AfterEventTrigger extends BaseAutomationTrigger
{
    public function __construct(
        private readonly CrmAutomationRepositoryInterface $automationRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly AutomationDefaults $defaults,
    ) {}

    public function dueRuns(CrmAutomationDomainObject $automation): array
    {
        $runs = [];

        foreach ($this->automationRepository->findFinishedOccurrences($automation, now()) as $occurrence) {
            $event = $this->eventRepository->findFirstWhere(['id' => (int) $occurrence->event_id]);

            if ($event === null) {
                continue;
            }

            $runs[] = new AutomationRunDTO(
                run_key: 'occurrence:'.$occurrence->id,
                label: $this->occurrenceLabel($event, $occurrence->start_date),
                event: $event,
                attributes: [
                    CrmCampaignDomainObjectAbstract::EVENT_OCCURRENCE_ID => (int) $occurrence->id,
                    CrmCampaignDomainObjectAbstract::SEGMENT_DEFINITION => $this->defaults->afterEventDefinition((int) $occurrence->event_id),
                ],
                keep_when_nobody_to_send_to: true,
            );
        }

        return $runs;
    }
}
