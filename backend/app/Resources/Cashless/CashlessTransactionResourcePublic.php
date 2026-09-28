<?php

declare(strict_types=1);

namespace HiEvents\Resources\Cashless;

use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin CashlessTransactionDomainObject
 */
class CashlessTransactionResourcePublic extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            /** @var 'TOPUP_ONLINE'|'TOPUP_STAFF'|'PURCHASE'|'REVERSAL'|'REFUND_REMAINING' */
            'type' => $this->getType(),
            'amount' => $this->getAmount(),
            'balance_after' => $this->getBalanceAfter(),
            'reverses_transaction_id' => $this->getReversesTransactionId(),
            'created_at' => $this->getCreatedAt(),
            $this->mergeWhen($this->getItems() !== null, fn () => [
                'items' => CashlessTransactionItemResource::collection($this->getItems()),
            ]),
            $this->mergeWhen($this->getWallet()?->getAttendee() !== null, fn () => [
                'attendee_public_id' => $this->getWallet()->getAttendee()->getPublicId(),
            ]),
            $this->mergeWhen($this->getSalesPoint() !== null, fn () => [
                'sales_point_name' => $this->getSalesPoint()->getName(),
            ]),
        ];
    }
}
