<?php

namespace HiEvents\Http\Request\Admin;

use HiEvents\DomainObjects\Enums\AdminDashboardPeriod;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class GetAdminDashboardDataRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', Rule::in(AdminDashboardPeriod::valuesArray())],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
