<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger;

use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationConfigDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationRunDTO;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Illuminate\Support\Carbon;

class BeforeEventTrigger extends BaseAutomationTrigger
{
    public const REMINDER_SEND_HOUR = 10;

    public const REMINDER_CATCH_UP_HOURS = 6;

    public const RUN_KEY_PREFIX = 'before:';

    public function __construct(
        private readonly CrmAutomationRepositoryInterface $automationRepository,
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    public static function reminderSendTime(string $startDateUtc, ?string $timezone, int $daysBefore): Carbon
    {
        return Carbon::parse($startDateUtc, 'UTC')
            ->setTimezone($timezone ?? 'UTC')
            ->subDays($daysBefore)
            ->setTime(self::REMINDER_SEND_HOUR, 0)
            ->utc();
    }

    public function dueRuns(CrmAutomationDomainObject $automation): array
    {
        $config = AutomationConfigDTO::forAutomation($automation);
        $now = now();
        $occurrences = $this->automationRepository->findOccurrencesDueAReminder(
            $automation,
            $config->days_before,
            self::REMINDER_SEND_HOUR,
            $now->copy()->subHours(self::REMINDER_CATCH_UP_HOURS),
            $now,
        );
        $runs = [];

        foreach ($occurrences as $occurrence) {
            $event = $this->eventRepository->findFirstWhere(['id' => (int) $occurrence->event_id]);

            if ($event === null) {
                continue;
            }

            $runs[] = new AutomationRunDTO(
                run_key: self::RUN_KEY_PREFIX.$occurrence->id,
                label: $this->occurrenceLabel($event, $occurrence->start_date),
                event: $event,
                attributes: [
                    ...$this->audienceAttributes($automation),
                    CrmCampaignDomainObjectAbstract::EVENT_ID => $event->getId(),
                    CrmCampaignDomainObjectAbstract::EVENT_OCCURRENCE_ID => (int) $occurrence->id,
                    CrmCampaignDomainObjectAbstract::EXCLUDES_OCCURRENCE_HOLDERS => $config->targetsNonBuyers(),
                ],
                keep_when_nobody_to_send_to: true,
            );
        }

        return $runs;
    }
}
