<?php

declare(strict_types=1);

namespace TicketKitten\Http\Request\Event;

use TicketKitten\Http\Request\BaseRequest;
use TicketKitten\Validator\EventRules;

class UpdateEventRequest extends BaseRequest
{
    use EventRules;

    public function rules(): array
    {
        $rules =  $this->eventRules();
        unset($rules['organizer_id']);

        return $rules;
    }

    public function messages(): array
    {
        return $this->eventMessages();
    }
}
