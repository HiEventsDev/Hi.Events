<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger;

use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationConfigDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationRunDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignSendGate;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Illuminate\Support\Carbon;

class AbandonedCheckoutTrigger extends BaseAutomationTrigger
{
    public const ABANDONED_LOOKBACK_HOURS = 24;

    public function __construct(
        private readonly CrmAutomationRepositoryInterface $automationRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly CampaignSendGate $sendGate,
    ) {}

    public function dueRuns(CrmAutomationDomainObject $automation): array
    {
        $config = AutomationConfigDTO::forAutomation($automation);
        $until = now()->subMinutes($config->wait_minutes);
        $from = max(Carbon::parse($automation->getEnabledAt(), 'UTC'), $until->copy()->subHours(self::ABANDONED_LOOKBACK_HOURS));

        if ($from->gte($until)) {
            return [];
        }

        $contactsByEvent = $this->automationRepository
            ->findAbandonedCheckoutContacts($automation, $from, $until)
            ->groupBy('event_id');
        $maxContactsPerRun = $this->sendGate->maxRecipientsPerSend($automation->getAccountId());
        $runs = [];

        foreach ($contactsByEvent as $eventId => $rows) {
            $event = $this->eventRepository->findFirstWhere(['id' => (int) $eventId]);

            if ($event === null) {
                continue;
            }

            $runs[] = new AutomationRunDTO(
                run_key: 'abandon:'.$eventId.':'.$this->runSlot(),
                label: $event->getTitle(),
                event: $event,
                attributes: [
                    ...$this->audienceAttributes($automation),
                    CrmCampaignDomainObjectAbstract::EVENT_ID => (int) $eventId,
                    CrmCampaignDomainObjectAbstract::AUDIENCE_IS_EXPLICIT => true,
                    CrmCampaignDomainObjectAbstract::SKIPS_BUYERS => true,
                ],
                contact_ids: $rows->pluck('contact_id')->take($maxContactsPerRun)->all(),
            );
        }

        return $runs;
    }
}
