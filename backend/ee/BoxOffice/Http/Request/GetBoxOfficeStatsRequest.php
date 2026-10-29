<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class GetBoxOfficeStatsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
