<?php

namespace HiEvents\Http\Request\Event;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\EventRules;

class DuplicateEventRequest extends BaseRequest
{
    use EventRules;

    public function rules(): array
    {
        $eventValidations = $this->minimalRules();

        $duplicateValidations = [
            'duplicate_tickets' => ['boolean', 'required'],
            'duplicate_questions' => ['boolean', 'required'],
            'duplicate_settings' => ['boolean', 'required'],
            'duplicate_promo_codes' => ['boolean', 'required'],
        ];

        return array_merge($eventValidations, $duplicateValidations);
    }

    public function messages(): array
    {
        return $this->eventMessages();
    }
}
