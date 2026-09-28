<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Cashless;

use HiEvents\Http\Request\BaseRequest;

class CreateCashlessQuoteRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'topup_amount' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.product_price_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
