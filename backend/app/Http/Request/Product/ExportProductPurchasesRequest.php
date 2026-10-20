<?php

namespace HiEvents\Http\Request\Product;

use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\DomainObjects\Status\ProductPurchaseStatus;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class ExportProductPurchasesRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer'],
            'event_occurrence_id' => ['nullable', 'integer'],
            'statuses' => ['nullable', 'array', 'max:'.count(ProductPurchaseStatus::cases())],
            'statuses.*' => ['string', Rule::in(ProductPurchaseStatus::valuesArray())],
            'refund_statuses' => ['nullable', 'array', 'max:'.count(OrderRefundStatus::cases())],
            'refund_statuses.*' => ['string', Rule::in(array_column(OrderRefundStatus::cases(), 'name'))],
            'query' => ['nullable', 'string', 'max:255'],
        ];
    }
}
