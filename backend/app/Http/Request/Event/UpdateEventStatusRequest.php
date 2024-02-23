<?php

namespace TicketKitten\Http\Request\Event;

use Illuminate\Validation\Rule;
use TicketKitten\DomainObjects\Status\EventStatus;
use TicketKitten\Http\Request\BaseRequest;

class UpdateEventStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(EventStatus::valuesArray())],
        ];
    }
}
