<?php

namespace HiEvents\Services\Domain\Cashless\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\CashlessStaffPaymentMethod;
use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use Illuminate\Support\Collection;

class RecordCashlessTransactionDTO extends BaseDataObject
{
    public function __construct(
        public int $wallet_id,
        public CashlessTransactionType $type,
        public float $positive_amount,
        public ?int $order_id = null,
        public ?int $sales_point_id = null,
        public ?int $created_by_user_id = null,
        public ?CashlessStaffPaymentMethod $staff_payment_method = null,
        public ?string $client_reference_id = null,
        public ?string $notes = null,
        /** @var Collection<CashlessTransactionItemDTO>|null */
        public ?Collection $items = null,
    ) {}
}
