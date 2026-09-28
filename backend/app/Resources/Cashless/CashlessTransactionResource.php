<?php

declare(strict_types=1);

namespace HiEvents\Resources\Cashless;

use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin CashlessTransactionDomainObject
 */
class CashlessTransactionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'cashless_wallet_id' => $this->getCashlessWalletId(),
            /** @var 'TOPUP_ONLINE'|'TOPUP_STAFF'|'PURCHASE'|'REVERSAL'|'REFUND_REMAINING' */
            'type' => $this->getType(),
            'amount' => $this->getAmount(),
            'balance_after' => $this->getBalanceAfter(),
            'order_id' => $this->getOrderId(),
            'cashless_sales_point_id' => $this->getCashlessSalesPointId(),
            /** @var 'CASH'|'CARD_TERMINAL'|'OTHER'|null */
            'staff_payment_method' => $this->getStaffPaymentMethod(),
            'reverses_transaction_id' => $this->getReversesTransactionId(),
            'notes' => $this->getNotes(),
            'created_at' => $this->getCreatedAt(),
            'items' => $this->when(
                $this->getItems() !== null,
                fn () => CashlessTransactionItemResource::collection($this->getItems()),
            ),
            $this->mergeWhen($this->getSalesPoint() !== null, fn () => [
                'sales_point_name' => $this->getSalesPoint()->getName(),
            ]),
            $this->mergeWhen($this->getWallet()?->getAttendee() !== null, fn () => [
                'attendee_public_id' => $this->getWallet()->getAttendee()->getPublicId(),
                'attendee_name' => trim(
                    $this->getWallet()->getAttendee()->getFirstName().' '.$this->getWallet()->getAttendee()->getLastName()
                ),
            ]),
        ];
    }
}
