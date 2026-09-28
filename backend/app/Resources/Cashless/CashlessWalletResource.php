<?php

declare(strict_types=1);

namespace HiEvents\Resources\Cashless;

use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin CashlessWalletDomainObject
 */
class CashlessWalletResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'event_id' => $this->getEventId(),
            'attendee_id' => $this->getAttendeeId(),
            'balance' => $this->getBalance(),
            'total_topped_up' => $this->getTotalToppedUp(),
            'total_spent' => $this->getTotalSpent(),
            'total_refunded' => $this->getTotalRefunded(),
            'currency' => $this->getCurrency(),
            /** @var 'ACTIVE'|'FROZEN'|'CLOSED' */
            'status' => $this->getStatus(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
            $this->mergeWhen($this->getAttendee() !== null, fn () => [
                'attendee_public_id' => $this->getAttendee()->getPublicId(),
                'attendee_first_name' => $this->getAttendee()->getFirstName(),
                'attendee_last_name' => $this->getAttendee()->getLastName(),
                'attendee_email' => $this->getAttendee()->getEmail(),
            ]),
            'transactions' => $this->when(
                $this->getTransactions() !== null,
                fn () => CashlessTransactionResource::collection($this->getTransactions()),
            ),
        ];
    }
}
