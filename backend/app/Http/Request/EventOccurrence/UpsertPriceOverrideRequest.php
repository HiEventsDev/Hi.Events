<?php

namespace HiEvents\Http\Request\EventOccurrence;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\RulesHelper;

class UpsertPriceOverrideRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'product_price_id' => ['required', 'integer'],
            'price' => ['nullable', 'required_without:quantity_available', 'numeric', 'min:0', 'max:100000000'],
            'quantity_available' => ['nullable', 'required_without:price', ...RulesHelper::INTEGER, 'min:0'],
        ];
    }
}
