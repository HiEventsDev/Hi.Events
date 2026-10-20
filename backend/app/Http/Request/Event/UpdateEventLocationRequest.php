<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Event;

use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateEventLocationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'event_location' => ['nullable', 'array'],
            'event_location.type' => ['required_with:event_location', Rule::in(LocationType::valuesArray())],
            'event_location.location_id' => [
                'nullable', 'integer',
                'required_if:event_location.type,'.LocationType::IN_PERSON->name,
            ],
            'event_location.online_event_connection_details' => [
                'nullable', 'string', 'max:10000',
                'required_if:event_location.type,'.LocationType::ONLINE->name,
            ],
            'clear_event_location' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'event_location.location_id.required_if' => __('A saved location must be selected for in-person events'),
        ];
    }
}
