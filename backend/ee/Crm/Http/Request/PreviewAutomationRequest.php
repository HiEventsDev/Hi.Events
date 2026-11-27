<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationConfigDTO;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class PreviewAutomationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'trigger' => ['required', 'string', Rule::in(CrmAutomationTrigger::valuesArray())],
            'automation_id' => ['nullable', 'integer', 'min:1'],
            ...$this->contentRules(),
        ];
    }

    protected function contentRules(): array
    {
        return [
            'subject' => ['required', 'string', 'min:1', 'max:200'],
            'preheader' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'min:1', 'max:100000'],
            'segment_id' => ['nullable', 'integer', 'min:1'],
            'preset' => ['nullable', 'string', 'max:50'],
            'delay_days' => ['nullable', 'integer', 'min:0', 'max:30'],
            'wait_minutes' => ['nullable', 'integer', Rule::in(AutomationConfigDTO::WAIT_MINUTES_OPTIONS)],
            'days_before' => ['nullable', 'integer', 'min:'.AutomationConfigDTO::MIN_DAYS_BEFORE, 'max:'.AutomationConfigDTO::MAX_DAYS_BEFORE],
            'occurrence_audience' => ['nullable', 'string', Rule::in([AutomationConfigDTO::OCCURRENCE_AUDIENCE_NOT_PURCHASED, AutomationConfigDTO::OCCURRENCE_AUDIENCE_PURCHASED])],
        ];
    }

    public function config(CrmAutomationTrigger $trigger): AutomationConfigDTO
    {
        $defaults = new AutomationConfigDTO(delay_days: $trigger->defaultDelayDays());

        return new AutomationConfigDTO(
            delay_days: (int) ($this->validated('delay_days') ?? $defaults->delay_days),
            wait_minutes: (int) ($this->validated('wait_minutes') ?? $defaults->wait_minutes),
            days_before: (int) ($this->validated('days_before') ?? $defaults->days_before),
            occurrence_audience: (string) ($this->validated('occurrence_audience') ?? $defaults->occurrence_audience),
        );
    }
}
