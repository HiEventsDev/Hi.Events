<?php

namespace HiEvents\Http\Request\Event;

use Illuminate\Validation\Rule;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Http\Request\BaseRequest;

class UpdateEventStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(EventStatus::valuesArray())],
        ];
    }
}
