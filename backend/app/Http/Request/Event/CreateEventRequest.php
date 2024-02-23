<?php

declare(strict_types=1);

namespace TicketKitten\Http\Request\Event;

use TicketKitten\Http\Request\BaseRequest;
use TicketKitten\Validator\EventRules;

class CreateEventRequest extends BaseRequest
{
    use EventRules;

    public function rules(): array
    {
        return $this->eventRules();
    }

    public function messages(): array
    {
        return $this->eventMessages();
    }
}
