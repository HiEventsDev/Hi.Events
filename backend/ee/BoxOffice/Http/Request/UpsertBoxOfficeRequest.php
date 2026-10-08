<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Request;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\RulesHelper;
use Illuminate\Validation\Rule;

class UpsertBoxOfficeRequest extends BaseRequest
{
    public function rules(): array
    {
        $eventId = $this->route('event_id');

        return [
            'name' => RulesHelper::REQUIRED_STRING,
            'description' => ['nullable', 'string', 'max:2000'],
            'product_ids' => ['nullable', 'array'],
            'event_occurrence_id' => [
                'nullable',
                'integer',
                Rule::exists('event_occurrences', 'id')
                    ->where('event_id', $eventId)
                    ->whereNull('deleted_at'),
            ],
            'check_in_list_id' => [
                'nullable',
                'integer',
                Rule::exists('check_in_lists', 'id')
                    ->where('event_id', $eventId)
                    ->whereNull('deleted_at'),
            ],
            'allow_price_override' => ['nullable', 'boolean'],
            'allow_discounts' => ['nullable', 'boolean'],
            'collect_order_questions' => ['nullable', 'boolean'],
            'activates_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
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
            'expires_at.after' => __('The expiration date must be after the activation date.'),
            'activates_at.before' => __('The activation date must be before the expiration date.'),
        ];
    }
}
