<?php

namespace HiEvents\Http\Request\OrganizerSubscription;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class GetOrganizerSubscriptionsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'query' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::in(['confirmed', 'pending', 'unsubscribed'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
