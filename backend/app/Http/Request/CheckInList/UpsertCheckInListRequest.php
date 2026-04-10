<?php

namespace HiEvents\Http\Request\CheckInList;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\RulesHelper;
use Illuminate\Validation\Rule;

class UpsertCheckInListRequest extends BaseRequest
{
    public function rules(): array
    {
        $eventId = $this->route('event_id');

        return [
            'name' => RulesHelper::REQUIRED_STRING,
            'description' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
            'activates_at' => ['nullable', 'date'],
            'product_ids' => ['required', 'array', 'min:1'],
            'event_occurrence_id' => [
                'nullable',
                'integer',
                Rule::exists('event_occurrences', 'id')
                    ->where('event_id', $eventId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->sometimes('expires_at', 'after:activates_at', function ($input) {
            return $input->activates_at !== null && $input->expires_at !== null;
        });

        $validator->sometimes('activates_at', 'before:expires_at', function ($input) {
            return $input->activates_at !== null && $input->expires_at !== null;
        });
    }

    public function messages(): array
    {
        return [
            'product_ids.required' => __('Please select at least one product.'),
            'expires_at.after' => __('The expiration date must be after the activation date.'),
            'activates_at.before' => __('The activation date must be before the expiration date.'),
        ];
    }
}
