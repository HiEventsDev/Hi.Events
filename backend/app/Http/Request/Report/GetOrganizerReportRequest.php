<?php

namespace HiEvents\Http\Request\Report;

use HiEvents\Http\Request\BaseRequest;

class GetOrganizerReportRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'start_date' => 'date|before:end_date|required_with:end_date|nullable',
            'end_date' => 'date|after:start_date|required_with:start_date|nullable',
            'currency' => 'string|size:3|nullable',
            'event_id' => 'integer|nullable',
            'page' => 'integer|min:1|nullable',
            'per_page' => 'integer|min:1|max:1000|nullable',
        ];
    }
}
