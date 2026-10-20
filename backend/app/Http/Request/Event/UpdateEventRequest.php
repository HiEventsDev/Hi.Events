<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Event;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\EventRules;

class UpdateEventRequest extends BaseRequest
{
    use EventRules;

    public function rules(): array
    {
        $rules = $this->eventRules();
        unset($rules['organizer_id'], $rules['event_location'], $rules['event_location.type'], $rules['event_location.location_id'], $rules['event_location.online_event_connection_details']);

        return $rules;
    }

    public function messages(): array
    {
        return $this->eventMessages();
    }
}
