<?php

namespace HiEvents\Http\Request\Waitlist;

use HiEvents\Http\Request\BaseRequest;

class CreateWaitlistEntryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'product_price_id' => ['required', 'integer', 'exists:product_prices,id'],
            'email' => ['required', 'email', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
