<?php

declare(strict_types=1);

namespace HiEvents\Validators\Rules;

use HiEvents\DomainObjects\Status\AffiliateStatus;
use Illuminate\Validation\Rule;

class AffiliateRules
{
    public static function createRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', Rule::in(AffiliateStatus::valuesArray())],
        ];
    }

    public static function updateRules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', Rule::in(AffiliateStatus::valuesArray())],
        ];
    }
}