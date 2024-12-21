<?php

namespace HiEvents\Http\Request\Product;

use Illuminate\Foundation\Http\FormRequest;

class SortProductsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sorted_categories' => 'array|required',
            'sorted_categories.*.product_category_id' => 'integer|required',
            'sorted_categories.*.sorted_products' => 'array',
            'sorted_categories.*.sorted_products.*.id' => 'integer|required',
            'sorted_categories.*.sorted_products.*.order' => 'integer',
        ];
    }
}
