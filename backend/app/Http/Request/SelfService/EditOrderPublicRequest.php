<?php

namespace HiEvents\Http\Request\SelfService;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Validators\Rules\PersonNameRule;

class EditOrderPublicRequest extends BaseRequest
{
    private ?OrderDomainObject $order = null;

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:255', new PersonNameRule($this->order()?->getFirstName())],
            'last_name' => ['sometimes', 'string', 'max:255', new PersonNameRule($this->order()?->getLastName())],
            'email' => ['sometimes', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.string' => __('First name must be a string'),
            'first_name.max' => __('First name must not exceed 255 characters'),
            'last_name.string' => __('Last name must be a string'),
            'last_name.max' => __('Last name must not exceed 255 characters'),
            'email.email' => __('Email must be a valid email address'),
            'email.max' => __('Email must not exceed 255 characters'),
        ];
    }

    private function order(): ?OrderDomainObject
    {
        return $this->order ??= app(OrderRepositoryInterface::class)->findFirstWhere([
            'short_id' => $this->route('order_short_id'),
            'event_id' => $this->route('event_id'),
        ]);
    }
}
