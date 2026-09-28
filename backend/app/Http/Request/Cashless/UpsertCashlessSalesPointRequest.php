<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Cashless;

use HiEvents\Http\Request\BaseRequest;

class UpsertCashlessSalesPointRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'product_ids' => ['present', 'array'],
            'product_ids.*' => ['required', 'integer'],
            'allow_staff_topups' => ['required', 'boolean'],
            'access_pin' => ['nullable', 'string', 'min:4', 'max:32'],
            'activates_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:activates_at'],
        ];
    }
}
