<?php

namespace HiEvents\Resources\Order;

use Carbon\Carbon;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\SeatClaimDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Resources\Attendee\AttendeeResourcePublic;
use HiEvents\Resources\BaseResource;
use HiEvents\Resources\Event\EventResourcePublic;
use HiEvents\Resources\Order\Invoice\InvoiceResourcePublic;
use Illuminate\Http\Request;

/**
 * @mixin OrderDomainObject
 */
class OrderResourcePublic extends BaseResource
{
    public function toArray(Request $request): array
    {
        $includePostCheckoutData = $this->getStatus() === OrderStatus::COMPLETED->name;

        return [
            'short_id' => $this->getShortId(),
            'total_before_additions' => $this->getTotalBeforeAdditions(),
            'total_tax' => $this->getTotalTax(),
            'total_gross' => $this->getTotalGross(),
            'total_fee' => $this->getTotalFee(),
            /** @var 'RESERVED'|'CANCELLED'|'COMPLETED'|'AWAITING_OFFLINE_PAYMENT'|'ABANDONED' */
            'status' => $this->getStatus(),
            /** @var 'REFUND_PENDING'|'REFUND_FAILED'|'REFUNDED'|'PARTIALLY_REFUNDED'|null */
            'refund_status' => $this->getRefundStatus(),
            /** @var 'NO_PAYMENT_REQUIRED'|'AWAITING_PAYMENT'|'AWAITING_OFFLINE_PAYMENT'|'PAYMENT_FAILED'|'PAYMENT_RECEIVED'|null */
            'payment_status' => $this->getPaymentStatus(),
            'currency' => $this->getCurrency(),
            'reserved_until' => $this->getReservedUntil(),
            'is_expired' => $this->when(
                ! is_null($this->getReservedUntil()),
                fn () => Carbon::createFromTimeString($this->getReservedUntil())->isPast(),
            ),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'email' => $this->getEmail(),
            'public_id' => $this->getPublicId(),
            'is_payment_required' => $this->isPaymentRequired(),
            'promo_code' => $this->getPromoCode(),
            'taxes_and_fees_rollup' => $this->getTaxesAndFeesRollup(),
            'event' => $this->when(
                ! is_null($this->getEvent()),
                fn () => new EventResourcePublic(
                    resource: $this->getEvent(),
                    includePostCheckoutData: $includePostCheckoutData,
                ),
            ),
            'latest_invoice' => $this->when(
                ! is_null($this->getLatestInvoice()),
                fn () => (new InvoiceResourcePublic($this->getLatestInvoice()))->toArray($request),
            ),
            'address' => $this->when(
                ! is_null($this->getAddress()),
                fn () => $this->getAddress()
            ),
            'order_items' => $this->when(
                ! is_null($this->getOrderItems()),
                fn () => $this->getOrderItems()->map(
                    fn ($orderItem) => new OrderItemResourcePublic($orderItem, $includePostCheckoutData),
                )
            ),
            'attendees' => $this->when(
                ! is_null($this->getAttendees()),
                fn () => $this->getAttendees()->map(
                    fn ($attendee) => new AttendeeResourcePublic($attendee, $includePostCheckoutData),
                )
            ),
            'seats' => $this->when(
                ! is_null($this->getSeatClaims()),
                fn () => $this->getSeatClaims()->map(fn (SeatClaimDomainObject $claim) => [
                    'seat_uid' => $claim->getSeatUid(),
                    'seat_label' => $claim->getSeatLabel(),
                    'order_item_id' => $claim->getOrderItemId(),
                ])->values()
            ),
            $this->mergeWhen($this->getSessionIdentifier() !== null, fn () => [
                'session_identifier' => $this->getSessionIdentifier(),
            ]),
            $this->mergeWhen($this->isBoxOfficeOrder() && $request->attributes->has(AuthenticateBoxOfficeSession::SESSION_ATTRIBUTE), fn () => [
                'created_at' => $this->getCreatedAt(),
                'box_office_operator_name' => $this->getBoxOfficeOperatorName(),
                /** @var 'CASH'|'CARD'|'COMP'|'OTHER'|'FREE'|null */
                'box_office_tender' => $this->getBoxOfficeTender(),
                'box_office_amount_tendered' => $this->getBoxOfficeAmountTendered(),
                'box_office_change_due' => $this->getBoxOfficeChangeDue(),
                'box_office_reference' => $this->getBoxOfficeReference(),
                'box_office_card_error' => $this->cardError(),
            ]),
        ];
    }

    private function cardError(): ?array
    {
        $error = $this->getStripePayment()?->getLastError();

        if ($error === null) {
            return null;
        }

        return [
            'code' => $error['decline_code'] ?? $error['code'] ?? null,
            'message' => $error['message'] ?? null,
            'charge_id' => $error['charge_id'] ?? null,
        ];
    }
}
