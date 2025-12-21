<?php

namespace HiEvents\Resources\Order;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin OrderDomainObject
 */
class AdminOrderResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $event = $this->getEvent();
        $account = $event?->getAccount();

        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'public_id' => $this->getPublicId(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'email' => $this->getEmail(),
            'total_gross' => $this->getTotalGross(),
            'total_tax' => $this->getTotalTax(),
            'total_fee' => $this->getTotalFee(),
            'currency' => $this->getCurrency(),
            'status' => $this->getStatus(),
            'payment_status' => $this->getPaymentStatus(),
            'created_at' => $this->getCreatedAt(),
            'event_id' => $this->getEventId(),
            'event_title' => $event?->getTitle(),
            'account_id' => $account?->getId(),
            'account_name' => $account?->getName(),
        ];
    }
}
