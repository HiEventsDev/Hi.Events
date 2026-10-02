<?php

namespace HiEvents\Http\Request\Attendee;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Validators\Rules\RulesHelper;

class EditAttendeeRequest extends BaseRequest
{
    private ?bool $isBoxOfficeAttendee = null;

    public function rules(): array
    {
        return [
            'email' => $this->isBoxOfficeAttendee() ? RulesHelper::OPTIONAL_EMAIL : RulesHelper::REQUIRED_EMAIL,
            'first_name' => RulesHelper::REQUIRED_STRING,
            'last_name' => $this->isBoxOfficeAttendee() ? RulesHelper::STRING : RulesHelper::REQUIRED_STRING,
            'product_id' => RulesHelper::REQUIRED_NUMERIC,
            'product_price_id' => RulesHelper::REQUIRED_NUMERIC,
            'notes' => RulesHelper::OPTIONAL_TEXT_MEDIUM_LENGTH,
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('Email is required'),
            'email.email' => __('Email must be a valid email address'),
            'first_name.required' => __('First name is required'),
            'last_name.required' => __('Last name is required'),
            'product_id.required' => __('Product is required'),
            'product_price_id.required' => __('Product price is required'),
            'product_id.numeric' => '',
            'product_price_id.numeric' => '',
            'notes.max' => __('Notes must be less than 2000 characters'),
        ];
    }

    private function isBoxOfficeAttendee(): bool
    {
        if ($this->isBoxOfficeAttendee !== null) {
            return $this->isBoxOfficeAttendee;
        }

        $attendee = app(AttendeeRepositoryInterface::class)
            ->loadRelation(new Relationship(domainObject: OrderDomainObject::class, name: 'order'))
            ->findFirstWhere([
                'id' => $this->route('attendee_id'),
                'event_id' => $this->route('event_id'),
            ]);

        return $this->isBoxOfficeAttendee = $attendee?->getOrder()?->isBoxOfficeOrder() ?? false;
    }
}
