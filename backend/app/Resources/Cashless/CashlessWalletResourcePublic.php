<?php

declare(strict_types=1);

namespace HiEvents\Resources\Cashless;

use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin CashlessWalletDomainObject
 */
class CashlessWalletResourcePublic extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'balance' => $this->getBalance(),
            'total_topped_up' => $this->getTotalToppedUp(),
            'total_spent' => $this->getTotalSpent(),
            'total_refunded' => $this->getTotalRefunded(),
            'currency' => $this->getCurrency(),
            /** @var 'ACTIVE'|'FROZEN'|'CLOSED' */
            'status' => $this->getStatus(),
            $this->mergeWhen($this->getAttendee() !== null, fn () => [
                'attendee_public_id' => $this->getAttendee()->getPublicId(),
                'attendee_name' => trim($this->getAttendee()->getFirstName().' '.$this->getAttendee()->getLastName()),
            ]),
            $this->mergeWhen($this->getTransactions() !== null, fn () => [
                'transactions' => CashlessTransactionResourcePublic::collection($this->getTransactions()),
            ]),
        ];
    }
}
