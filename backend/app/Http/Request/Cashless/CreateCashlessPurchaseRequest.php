<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Cashless;

use HiEvents\Http\Request\BaseRequest;

class CreateCashlessPurchaseRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'attendee_public_id' => ['required', 'string', 'max:64'],
            'client_reference_id' => ['required', 'string', 'max:64'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.product_price_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
