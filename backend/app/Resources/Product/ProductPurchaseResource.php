<?php

namespace HiEvents\Resources\Product;

use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductPurchaseDTO
 */
class ProductPurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'order_id' => $this->orderId,
            'order_public_id' => $this->orderPublicId,
            /** @var 'COMPLETED'|'CANCELLED'|'AWAITING_OFFLINE_PAYMENT' */
            'order_status' => $this->orderStatus,
            /** @var 'REFUND_PENDING'|'REFUND_FAILED'|'REFUNDED'|'PARTIALLY_REFUNDED'|null */
            'refund_status' => $this->refundStatus,
            'order_created_at' => $this->orderCreatedAt,
            'currency' => $this->currency,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'product_title' => $this->productTitle,
            'product_price_id' => $this->productPriceId,
            'price_label' => $this->priceLabel,
            'event_occurrence_id' => $this->eventOccurrenceId,
            'occurrence_start_date' => $this->occurrenceStartDate,
            'sold_quantity' => $this->soldQuantity,
            'awaiting_payment_quantity' => $this->awaitingPaymentQuantity,
            'cancelled_quantity' => $this->cancelledQuantity,
            'line_total' => $this->lineTotal,
            /** @var 'SOLD'|'AWAITING_PAYMENT'|'CANCELLED' */
            'status' => $this->status,
        ];
    }
}
