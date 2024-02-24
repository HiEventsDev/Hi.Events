<?php

namespace HiEvents\Resources\Order;

use Carbon\Carbon;
use Illuminate\Http\Request;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Resources\Attendee\AttendeeResourcePublic;
use HiEvents\Resources\BaseResource;

/**
 * @mixin OrderDomainObject
 */
class OrderResourcePublic extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'short_id' => $this->getShortId(),
            'total_before_additions' => $this->getTotalBeforeAdditions(),
            'total_tax' => $this->getTotalTax(),
            'total_gross' => $this->getTotalGross(),
            'total_fee' => $this->getTotalFee(),
            'status' => $this->getStatus(),
            'refund_status' => $this->getRefundStatus(),
            'payment_status' => $this->getPaymentStatus(),
            'currency' => $this->getCurrency(),
            'reserved_until' => $this->getReservedUntil(),
            'is_expired' => Carbon::createFromTimeString($this->getReservedUntil())->isPast(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'email' => $this->getEmail(),
            'public_id' => $this->getPublicId(),
            'is_payment_required' => $this->isPaymentRequired(),
            'promo_code' => $this->getPromoCode(),
            'taxes_and_fees_rollup' => $this->getTaxesAndFeesRollup(),
            'address' => $this->when(
                !is_null($this->getAddress()),
                fn() => $this->getAddress()
            ),
            'order_items' => $this->when(
                !is_null($this->getOrderItems()),
                fn() => OrderItemResourcePublic::collection($this->getOrderItems())
            ),
            'attendees' => $this->when(
                !is_null($this->getAttendees()),
                fn() => AttendeeResourcePublic::collection($this->getAttendees())
            ),
        ];
    }
}
