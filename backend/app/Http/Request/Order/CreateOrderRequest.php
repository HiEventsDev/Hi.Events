<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Order;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Services\Domain\Order\OrderCreateRequestValidationService;

class CreateOrderRequest extends BaseRequest
{
    /**
     * @see OrderCreateRequestValidationService
     */
    public function rules(): array
    {
        if ($this->route() !== null) {
            return [];
        }

        return [
            'products' => ['required', 'array'],
            'products.*.product_id' => ['required', 'integer'],
            'products.*.event_occurrence_id' => ['integer', 'nullable'],
            'products.*.quantities' => ['required', 'array'],
            'products.*.quantities.*.quantity' => ['required', 'integer', 'min:0'],
            'products.*.quantities.*.price_id' => ['required', 'integer'],
            'products.*.quantities.*.price' => ['numeric', 'min:0', 'nullable'],
            'promo_code' => ['nullable', 'string'],
            'affiliate_code' => ['nullable', 'string'],
        ];
    }
}
