<?php

namespace HiEvents\Http\Request\Message;

use HiEvents\DomainObjects\Enums\MessageTypeEnum;
use HiEvents\DomainObjects\Status\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class SendMessageRequest extends FormRequest
{
    public function rules(): array
    {
        $eventId = $this->route('event_id');

        return [
            'subject' => 'required|string|max:100',
            'message' => 'required|string|max:8000',
            'message_type' => [new In(MessageTypeEnum::valuesArray()), 'required'],
            'is_test' => 'boolean',
            'send_copy_to_current_user' => 'boolean',
            'attendee_ids' => 'max:50,array|required_if:message_type,'.MessageTypeEnum::INDIVIDUAL_ATTENDEES->name,
            'attendee_ids.*' => 'integer',
            'product_ids' => ['array', 'required_if:message_type,'.MessageTypeEnum::TICKET_HOLDERS->name],
            'order_id' => 'integer|required_if:message_type,'.MessageTypeEnum::ORDER_OWNER->name,
            'product_ids.*' => 'integer',
            'order_statuses.*' => [
                'required_if:message_type,'.MessageTypeEnum::ORDER_OWNERS_WITH_PRODUCT->name,
                new In([OrderStatus::COMPLETED->name, OrderStatus::AWAITING_OFFLINE_PAYMENT->name]),
            ],
            'scheduled_at' => 'nullable|date',
            'event_occurrence_id' => [
                'nullable',
                'integer',
                Rule::exists('event_occurrences', 'id')
                    ->where('event_id', $eventId)
                    ->whereNull('deleted_at'),
            ],
            // Targets attendees across multiple occurrences (e.g. after a bulk
            // reschedule). Mutually exclusive with event_occurrence_id.
            'event_occurrence_ids' => ['nullable', 'array', 'max:500'],
            'event_occurrence_ids.*' => [
                'integer',
                Rule::exists('event_occurrences', 'id')
                    ->where('event_id', $eventId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('event_occurrence_id') && $this->filled('event_occurrence_ids')) {
                $validator->errors()->add(
                    'event_occurrence_ids',
                    __('Only one of event_occurrence_id or event_occurrence_ids may be provided.')
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'order_statuses.required_if' => __('The order statuses field is required when sending messages to order owners with a specific product.'),
            'subject.max' => __('The subject must be less than 100 characters.'),
            'attendee_ids.max' => __('You can only send a message to a maximum of 50 individual attendees at a time. To message more attendees, you can send to attendees with a specific product, or to all event attendees.'),
            'event_occurrence_ids.max' => __('You can only target up to 500 occurrences in a single message.'),
        ];
    }
}
