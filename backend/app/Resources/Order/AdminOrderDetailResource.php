<?php

namespace HiEvents\Resources\Order;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin OrderDomainObject
 */
class AdminOrderDetailResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $event = $this->getEvent();
        $account = $event?->getAccount();
        $attendees = $this->getAttendees();

        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'public_id' => $this->getPublicId(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'email' => $this->getEmail(),
            'total_before_additions' => $this->getTotalBeforeAdditions(),
            'total_gross' => $this->getTotalGross(),
            'total_tax' => $this->getTotalTax(),
            'total_fee' => $this->getTotalFee(),
            'total_refunded' => $this->getTotalRefunded(),
            'currency' => $this->getCurrency(),
            'status' => $this->getStatus(),
            'payment_status' => $this->getPaymentStatus(),
            'payment_gateway' => $this->getPaymentGateway(),
            'promo_code' => $this->getPromoCode(),
            'address' => $this->getAddress(),
            'notes' => $this->getNotes(),
            'created_at' => $this->getCreatedAt(),
            'event_id' => $this->getEventId(),
            'event_title' => $event?->getTitle(),
            'account_id' => $account?->getId(),
            'account_name' => $account?->getName(),
            'attendees' => $attendees ? $attendees->map(fn($attendee) => [
                'id' => $attendee->getId(),
                'first_name' => $attendee->getFirstName(),
                'last_name' => $attendee->getLastName(),
                'email' => $attendee->getEmail(),
                'status' => $attendee->getStatus(),
                'public_id' => $attendee->getPublicId(),
                'short_id' => $attendee->getShortId(),
                'checked_in_at' => $attendee->getCheckedInAt(),
            ])->values()->toArray() : [],
        ];
    }
}
