<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Order;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Locale;
use Illuminate\Validation\Rule;

class CreateManualOrderRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:40'],
            'last_name' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email'],
            'send_confirmation_email' => ['sometimes', 'boolean'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'integer'],
            'products.*.quantities' => ['required', 'array', 'min:1'],
            'products.*.quantities.*.price_id' => ['required', 'integer'],
            'products.*.quantities.*.quantity' => ['required', 'integer', 'min:0'],
            'promo_code' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'locale' => ['sometimes', Rule::in(Locale::getSupportedLocales())],
        ];
    }
}
