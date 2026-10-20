<?php

namespace HiEvents\Http\Request\EventOccurrence;

use HiEvents\Http\Request\BaseRequest;

class CancelOccurrenceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'refund_orders' => ['nullable', 'boolean'],
        ];
    }
}
