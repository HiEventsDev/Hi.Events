<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Lineup;

use HiEvents\DomainObjects\Enums\LineupBillingTier;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateLineupEntryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'billing_tier' => ['required', Rule::enum(LineupBillingTier::class)],
            'role_label' => ['nullable', 'string', 'max:100'],
            'is_hidden' => ['boolean'],
        ];
    }
}
