<?php

namespace HiEvents\Http\Request\Message;

use HiEvents\DomainObjects\Enums\MessageTypeEnum;
use HiEvents\DomainObjects\Status\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\In;

class SendMessageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'subject' => 'required|string|max:100',
            'message' => 'required|string|max:5000',
            'message_type' => [new In(MessageTypeEnum::valuesArray()), 'required'],
            'is_test' => 'boolean',
            'attendee_ids' => 'max:50,array|required_if:message_type,' . MessageTypeEnum::INDIVIDUAL_ATTENDEES->name,
            'attendee_ids.*' => 'integer',
            'product_ids' => ['array', 'required_if:message_type,' . MessageTypeEnum::TICKET_HOLDERS->name],
            'order_id' => 'integer|required_if:message_type,' . MessageTypeEnum::ORDER_OWNER->name,
            'product_ids.*' => 'integer',
            'order_statuses.*' => [
                'required_if:message_type,' . MessageTypeEnum::ORDER_OWNERS_WITH_PRODUCT->name,
                new In([OrderStatus::COMPLETED->name, OrderStatus::AWAITING_OFFLINE_PAYMENT->name]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'order_statuses.required_if' => 'The order statuses field is required when sending messages to order owners with a specific product.',
            'subject.max' => 'The subject must be less than 100 characters.',
            'attendee_ids.max' => 'You can only send a message to a maximum of 50 individual attendees at a time. ' .
                'To message more attendees, you can send to attendees with a specific product, or to all event attendees.'
        ];
    }
}
