<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Admin;

use HiEvents\DomainObjects\Enums\AttributionGroupBy;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class GetUtmAttributionStatsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'group_by' => ['nullable', 'string', Rule::in(AttributionGroupBy::valuesArray())],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
