<?php

namespace HiEvents\Http\Request\Product;

use Illuminate\Foundation\Http\FormRequest;

class SortProductsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            '*.id' => 'integer|required',
            '*.order' => 'integer|required',
        ];
    }
}
