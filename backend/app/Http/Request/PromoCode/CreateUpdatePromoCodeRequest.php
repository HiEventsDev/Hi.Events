<?php

namespace HiEvents\Http\Request\PromoCode;

use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class CreateUpdatePromoCodeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code' => 'min:2|string|required|max:50',
            'applicable_product_ids' => 'array',
            'discount' => [
                'required_if:discount_type,PERCENTAGE,FIXED',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    if ($this->input('discount_type') === PromoCodeDiscountTypeEnum::PERCENTAGE->name && $value > 100) {
                        $fail('The discount percentage must be less than or equal to 100%.');
                    }
                },
            ],
            'expiry_date' => 'date|nullable',
            'valid_from' => 'date|nullable',
            'max_allowed_usages' => 'nullable|gte:1|max:9999999',
            'max_attendee_usages' => 'nullable|gte:1|max:9999999',
            'message' => 'string|nullable|max:2000',
            'discount_type' => [
                'required',
                Rule::in(PromoCodeDiscountTypeEnum::valuesArray())
            ],
        ];
    }
}
