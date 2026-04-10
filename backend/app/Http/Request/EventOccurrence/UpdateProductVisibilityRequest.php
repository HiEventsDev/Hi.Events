<?php

namespace HiEvents\Http\Request\EventOccurrence;

use HiEvents\Http\Request\BaseRequest;

class UpdateProductVisibilityRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['integer'],
        ];
    }
}
