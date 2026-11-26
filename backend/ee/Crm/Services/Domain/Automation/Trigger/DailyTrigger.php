<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\DomainObjects\Generated\CrmAutomationDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrganizerDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationRunDTO;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Support\Carbon;

class DailyTrigger extends BaseAutomationTrigger
{
    public const SEND_HOUR = 10;

    public function __construct(
        private readonly CrmAutomationRepositoryInterface $automationRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
    ) {}

    public static function sendTimeOn(CarbonInterface $day, string $timezone): Carbon
    {
        return Carbon::instance($day)->setTimezone($timezone)->setTime(self::SEND_HOUR, 0)->utc();
    }

    public static function hasRunOn(CarbonInterface $day, string $timezone, ?string $lastRunAt): bool
    {
        return $lastRunAt !== null
            && Carbon::parse($lastRunAt, 'UTC')->setTimezone($timezone)->toDateString() === Carbon::instance($day)->setTimezone($timezone)->toDateString();
    }

    public function dueRuns(CrmAutomationDomainObject $automation): array
    {
        $organizer = $this->organizerRepository->findFirstWhere([OrganizerDomainObjectAbstract::ID => $automation->getOrganizerId()]);

        if ($organizer === null) {
            return [];
        }

        $timezone = $organizer->getTimezone();
        $now = now();
        $today = $now->copy()->setTimezone($timezone)->toDateString();

        if (self::hasRunOn($now, $timezone, $automation->getLastRunAt()) || $now->lt(self::sendTimeOn($now, $timezone))) {
            return [];
        }

        return [new AutomationRunDTO(
            run_key: 'date:'.$today,
            label: $today,
            event: null,
            attributes: $this->audienceAttributes($automation),
        )];
    }

    public function settled(CrmAutomationDomainObject $automation, AutomationRunDTO $run): void
    {
        $this->automationRepository->updateWhere(
            attributes: [CrmAutomationDomainObjectAbstract::LAST_RUN_AT => now()->toDateTimeString()],
            where: [CrmAutomationDomainObjectAbstract::ID => $automation->getId()],
        );
    }
}
