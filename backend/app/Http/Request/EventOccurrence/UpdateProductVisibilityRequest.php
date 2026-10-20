<?php

namespace HiEvents\Http\Request\EventOccurrence;

use HiEvents\Http\Request\BaseRequest;

class UpdateProductVisibilityRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_ids.min' => __('Select at least one product. To make a date inaccessible, cancel it from the schedule instead.'),
        ];
    }
}
