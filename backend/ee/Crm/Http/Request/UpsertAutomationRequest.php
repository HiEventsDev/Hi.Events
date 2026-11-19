<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;
use Illuminate\Validation\Rule;

class UpsertAutomationRequest extends PreviewAutomationRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:80'],
            'trigger' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'string', Rule::in(CrmAutomationTrigger::valuesArray())],
            ...$this->contentRules(),
        ];
    }
}
