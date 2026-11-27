<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;

class AutomationConfigDTO extends BaseDataObject
{
    public const WAIT_MINUTES_OPTIONS = [60, 180, 1440];

    public const OCCURRENCE_AUDIENCE_NOT_PURCHASED = 'not_purchased';

    public const OCCURRENCE_AUDIENCE_PURCHASED = 'purchased';

    public const MIN_DAYS_BEFORE = 1;

    public const MAX_DAYS_BEFORE = 14;

    public function __construct(
        public readonly int $delay_days = 0,
        public readonly int $wait_minutes = 60,
        public readonly int $days_before = 3,
        public readonly string $occurrence_audience = self::OCCURRENCE_AUDIENCE_PURCHASED,
    ) {}

    public static function forAutomation(CrmAutomationDomainObject $automation): self
    {
        return self::fromStored($automation->getConfig(), (int) $automation->getDelayDays());
    }

    public static function fromStored(?array $config, int $delayDays): self
    {
        return new self(
            delay_days: $delayDays,
            wait_minutes: (int) ($config['wait_minutes'] ?? 60),
            days_before: (int) ($config['days_before'] ?? 3),
            occurrence_audience: (string) ($config['occurrence_audience'] ?? self::OCCURRENCE_AUDIENCE_PURCHASED),
        );
    }

    public function forStorage(CrmAutomationTrigger $trigger): ?array
    {
        return match ($trigger) {
            CrmAutomationTrigger::ABANDONED_CHECKOUT => ['wait_minutes' => $this->wait_minutes],
            CrmAutomationTrigger::BEFORE_EVENT => ['days_before' => $this->days_before, 'occurrence_audience' => $this->occurrence_audience],
            default => null,
        };
    }

    public function targetsNonBuyers(): bool
    {
        return $this->occurrence_audience === self::OCCURRENCE_AUDIENCE_NOT_PURCHASED;
    }
}
