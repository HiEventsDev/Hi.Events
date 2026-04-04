<?php

namespace HiEvents\Http\Request\EventOccurrence;

use HiEvents\Http\Request\BaseRequest;

class UpsertPriceOverrideRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'product_price_id' => ['required', 'integer'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ];
    }
}
