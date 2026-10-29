<?php

namespace HiEvents\Http\Request\Event;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateEventTypeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(EventType::valuesArray())],
        ];
    }
}
