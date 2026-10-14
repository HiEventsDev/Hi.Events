<?php

namespace HiEvents\Http\Request\Product;

use HiEvents\Http\Request\BaseRequest;

class GetProductPurchaseSummaryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'event_occurrence_id' => ['nullable', 'integer'],
        ];
    }
}
