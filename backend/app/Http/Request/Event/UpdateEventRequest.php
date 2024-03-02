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
        $rules =  $this->eventRules();
        unset($rules['organizer_id']);

        return $rules;
    }

    public function messages(): array
    {
        return $this->eventMessages();
    }
}
