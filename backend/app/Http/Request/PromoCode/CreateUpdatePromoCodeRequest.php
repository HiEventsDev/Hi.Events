<?php

namespace TicketKitten\Http\Request\PromoCode;

use Illuminate\Validation\Rule;
use TicketKitten\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use TicketKitten\Http\Request\BaseRequest;

class CreateUpdatePromoCodeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code' => 'string|required|max:50',
            'applicable_ticket_ids' => 'array',
            'discount' => 'numeric|gte:0|nullable|max:9999999999',
            'expiry_date' => 'date|nullable',
            'max_allowed_usages' => 'nullable|gte:1|max:9999999',
            'discount_type' => [
                'required',
                Rule::in(PromoCodeDiscountTypeEnum::valuesArray())
            ],
        ];
    }
}
