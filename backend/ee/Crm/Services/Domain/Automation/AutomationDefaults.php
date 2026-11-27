<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Automation;

use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;
use HiEvents\DomainObjects\Enums\CrmSegmentField;
use HiEvents\DomainObjects\Enums\CrmSegmentMatch;
use HiEvents\DomainObjects\Enums\CrmSegmentOperator;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationConfigDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationTemplateDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentReferenceDTO;

class AutomationDefaults
{
    public function audience(CrmAutomationTrigger $trigger, ?int $segmentId, ?string $preset): SegmentReferenceDTO
    {
        if ($trigger->audienceIsTicketHolders()) {
            return new SegmentReferenceDTO;
        }

        return $segmentId !== null
            ? new SegmentReferenceDTO(segment_id: $segmentId)
            : new SegmentReferenceDTO(preset: $preset ?? $trigger->defaultPreset()?->value);
    }

    /**
     * @return AutomationTemplateDTO[]
     */
    public function templates(): array
    {
        return array_map(function (CrmAutomationTrigger $trigger): AutomationTemplateDTO {
            $config = new AutomationConfigDTO(delay_days: $trigger->defaultDelayDays());

            return new AutomationTemplateDTO(
                trigger: $trigger->value,
                name: $this->name($trigger),
                subject: $this->subject($trigger),
                body: $this->body($trigger),
                preset: $trigger->defaultPreset()?->value,
                delay_days: $config->delay_days,
                wait_minutes: $config->wait_minutes,
                days_before: $config->days_before,
                occurrence_audience: $config->occurrence_audience,
            );
        }, CrmAutomationTrigger::cases());
    }

    public function afterEventDefinition(int $eventId): array
    {
        return [
            'match' => CrmSegmentMatch::ALL->value,
            'rules' => [[
                'field' => CrmSegmentField::TICKET_EVENT->value,
                'operator' => CrmSegmentOperator::IN->value,
                'value' => [$eventId],
            ]],
        ];
    }

    public function name(CrmAutomationTrigger $trigger): string
    {
        return match ($trigger) {
            CrmAutomationTrigger::EVENT_PUBLISHED => __('Announce new events'),
            CrmAutomationTrigger::AFTER_EVENT => __('Say thanks after an event'),
            CrmAutomationTrigger::BEFORE_EVENT => __('Remind people before an event'),
            CrmAutomationTrigger::ABANDONED_CHECKOUT => __('Recover abandoned checkouts'),
            CrmAutomationTrigger::SEGMENT_ENTERED => __('Email people who join a segment'),
            CrmAutomationTrigger::WIN_BACK => __('Win back lapsed regulars'),
            CrmAutomationTrigger::WELCOME => __('Welcome first-timers'),
        };
    }

    public function subject(CrmAutomationTrigger $trigger): string
    {
        return match ($trigger) {
            CrmAutomationTrigger::EVENT_PUBLISHED => __('Just announced: {{ event_title }}'),
            CrmAutomationTrigger::AFTER_EVENT => __('Thanks for coming to {{ event_title }}'),
            CrmAutomationTrigger::BEFORE_EVENT => __('{{ event_title }} is coming up'),
            CrmAutomationTrigger::ABANDONED_CHECKOUT => __('Still thinking about {{ event_title }}?'),
            CrmAutomationTrigger::SEGMENT_ENTERED => __('A note from {{ organizer_name }}'),
            CrmAutomationTrigger::WIN_BACK => __('It\'s been a while'),
            CrmAutomationTrigger::WELCOME => __('Thanks for joining us'),
        };
    }

    public function body(CrmAutomationTrigger $trigger): string
    {
        $paragraphs = match ($trigger) {
            CrmAutomationTrigger::EVENT_PUBLISHED => [
                __('We\'ve just announced {{ event_title }} on {{ event_date }}.'),
                __('Tickets are on sale now. We\'d love to see you there.'),
            ],
            CrmAutomationTrigger::AFTER_EVENT => [
                __('Thank you for being part of {{ event_title }}. We hope you had a great time.'),
                __('We\'ll let you know as soon as the next one is announced.'),
            ],
            CrmAutomationTrigger::BEFORE_EVENT => [
                __('Just a reminder that {{ event_title }} is on {{ event_date }}.'),
                __('We\'re looking forward to seeing you there.'),
            ],
            CrmAutomationTrigger::ABANDONED_CHECKOUT => [
                __('You started booking {{ event_title }} but didn\'t finish.'),
                __('Pick up where you left off whenever you\'re ready.'),
            ],
            CrmAutomationTrigger::SEGMENT_ENTERED => [
                __('Thanks for being part of {{ organizer_name }}.'),
                __('We\'ll keep you posted when we announce something new.'),
            ],
            CrmAutomationTrigger::WIN_BACK => [
                __('It\'s been a while since we last saw you, and we have new events coming up.'),
                __('Take a look at what\'s on. We\'d love to have you back.'),
            ],
            CrmAutomationTrigger::WELCOME => [
                __('Thanks for booking with {{ organizer_name }}. It\'s great to have you with us.'),
                __('We\'ll keep you posted when we announce something new.'),
            ],
        };

        return implode('', array_map(static fn (string $paragraph) => '<p>'.e($paragraph, false).'</p>', $paragraphs));
    }
}
