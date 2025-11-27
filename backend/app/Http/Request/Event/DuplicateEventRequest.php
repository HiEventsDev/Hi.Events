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
            'duplicate_products' => ['boolean', 'required'],
            'duplicate_questions' => ['boolean', 'required'],
            'duplicate_settings' => ['boolean', 'required'],
            'duplicate_promo_codes' => ['boolean', 'required'],
            'duplicate_capacity_assignments' => ['boolean', 'required'],
            'duplicate_check_in_lists' => ['boolean', 'required'],
            'duplicate_event_cover_image' => ['boolean', 'required'],
            'duplicate_webhooks' => ['boolean', 'required'],
            'duplicate_affiliates' => ['boolean', 'required'],
            'duplicate_ticket_logo' => ['boolean', 'required'],
        ];

        return array_merge($eventValidations, $duplicateValidations);
    }

    public function messages(): array
    {
        return $this->eventMessages();
    }
}
