<?php

namespace HiEvents\DomainObjects\Enums;

enum CrmAutomationTrigger: string
{
    use BaseEnum;

    case EVENT_PUBLISHED = 'EVENT_PUBLISHED';
    case AFTER_EVENT = 'AFTER_EVENT';
    case BEFORE_EVENT = 'BEFORE_EVENT';
    case ABANDONED_CHECKOUT = 'ABANDONED_CHECKOUT';
    case SEGMENT_ENTERED = 'SEGMENT_ENTERED';
    case WIN_BACK = 'WIN_BACK';
    case WELCOME = 'WELCOME';

    public function isEventScoped(): bool
    {
        return in_array($this, [self::EVENT_PUBLISHED, self::AFTER_EVENT, self::BEFORE_EVENT, self::ABANDONED_CHECKOUT], true);
    }

    public function repeatAfterDays(): ?int
    {
        return match ($this) {
            self::WIN_BACK => 180,
            self::WELCOME => 3650,
            default => null,
        };
    }

    public function defaultPreset(): ?CrmSegmentPreset
    {
        return match ($this) {
            self::WIN_BACK => CrmSegmentPreset::LAPSED,
            self::WELCOME => CrmSegmentPreset::FIRST_TIMERS,
            self::SEGMENT_ENTERED => CrmSegmentPreset::SUBSCRIBERS,
            default => null,
        };
    }

    public function defaultDelayDays(): int
    {
        return $this === self::AFTER_EVENT ? 1 : 0;
    }

    public function audienceIsTicketHolders(): bool
    {
        return $this === self::AFTER_EVENT;
    }
}
