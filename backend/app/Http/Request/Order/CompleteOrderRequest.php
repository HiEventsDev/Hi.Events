<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Order;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\CompleteOrderValidator;

class CompleteOrderRequest extends BaseRequest
{
    public function rules(): array
    {
        if ($this->route() === null) {
            return [
                'order.first_name' => ['required', 'string', 'max:40'],
                'order.last_name' => ['required', 'string', 'max:40'],
                'order.email' => ['required', 'email'],
                'order.email_confirmation' => ['required', 'email', 'same:order.email'],
                'order.questions' => ['array'],
                'order.address' => ['array'],
                'order.address.address_line_1' => ['nullable', 'string', 'max:255'],
                'order.address.address_line_2' => ['nullable', 'string', 'max:255'],
                'order.address.city' => ['nullable', 'string', 'max:85'],
                'order.address.state_or_region' => ['nullable', 'string', 'max:85'],
                'order.address.zip_or_postal_code' => ['nullable', 'string', 'max:85'],
                'order.address.country' => ['nullable', 'string', 'max:2'],
                'products' => ['array'],
            ];
        }

        return app(CompleteOrderValidator::class)->rules();
    }

    public function messages(): array
    {
        return app(CompleteOrderValidator::class)->messages();
    }
}
