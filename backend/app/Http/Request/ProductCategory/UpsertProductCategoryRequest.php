<?php

namespace HiEvents\Http\Request\ProductCategory;

use HiEvents\Http\Request\BaseRequest;

class UpsertProductCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['string', 'required', 'max:50'],
            'description' => ['string', 'max:5000', 'nullable'],
            'is_hidden' => ['boolean', 'required'],
            'no_products_message' => ['string', 'max:255', 'nullable'],
        ];
    }
}
