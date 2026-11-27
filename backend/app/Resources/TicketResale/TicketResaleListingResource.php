<?php

namespace HiEvents\Resources\TicketResale;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\TicketResaleListingDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin TicketResaleListingDomainObject
 */
class TicketResaleListingResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $product = $this->getAttendee()?->getProduct();

        return [
            'id' => $this->getId(),
            /** @var 'LISTED'|'SOLD'|'CLOSED' */
            'status' => $this->getStatus(),
            /** @var 'OPEN'|'HELD'|'WITHDRAWAL_REQUESTED'|'NOT_OFFERED'|'SOLD'|'CLOSED' */
            'state' => $this->getState()?->value,
            /** @var 'WITHDRAWN'|'CUTOFF_PASSED'|'RESALE_TURNED_OFF'|'TICKET_CANCELLED'|'TICKET_USED'|'TICKET_TYPE_CHANGED'|'DATE_CANCELLED'|'CLOSED_BY_ORGANIZER'|null */
            'closed_reason' => $this->getClosedReason(),
            'needs_attention' => $this->hasFailedRefund(),
            'attendee' => [
                'id' => $this->getAttendeeId(),
                'public_id' => $this->getAttendee()?->getPublicId(),
                'first_name' => $this->getAttendee()?->getFirstName(),
                'last_name' => $this->getAttendee()?->getLastName(),
                'email' => $this->getAttendee()?->getEmail(),
            ],
            'order' => [
                'id' => $this->getOrderId(),
                'public_id' => $this->getOrder()?->getPublicId(),
                'short_id' => $this->when(
                    $this->isPermitted(Permission::EDIT) && $this->isPermitted(Permission::VIEW_FINANCIALS),
                    fn () => $this->getOrder()?->getShortId(),
                ),
            ],
            'sold_order' => $this->getSoldOrder() === null ? null : [
                'id' => $this->getSoldOrder()->getId(),
                'public_id' => $this->getSoldOrder()->getPublicId(),
            ],
            'product_title' => $product?->getTitle(),
            'price_label' => $product?->getProductPrices()
                ?->first(fn (ProductPriceDomainObject $price) => $price->getId() === $this->getProductPriceId())
                ?->getLabel(),
            'event_occurrence_id' => $this->getEventOccurrenceId(),
            'held_until' => $this->getHeldUntil(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
            $this->mergeWhen($this->isPermitted(Permission::VIEW_FINANCIALS), fn () => [
                'currency' => $this->getOrder()?->getCurrency(),
                'estimated_refund' => $this->getEstimatedRefund(),
                'refund_amount' => $this->getRefundAmount(),
                /** @var 'PENDING'|'SUCCEEDED'|'FAILED'|null */
                'refund_status' => $this->getRefundStatus(),
            ]),
        ];
    }
}
