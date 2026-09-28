<?php

namespace HiEvents\Services\Domain\Cashless\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CashlessSummaryDTO extends BaseDataObject
{
    public function __construct(
        public int $wallets_total,
        public int $wallets_with_balance,
        public int $wallets_frozen,
        public int $wallets_closed,
        public float $outstanding_balance,
        public float $topped_up_online,
        public float $topped_up_staff,
        public float $spent,
        public float $refunded,
        public float $closed,
        public int $purchases_count,
        /** @var array<CashlessSalesPointSummaryDTO> */
        public array $sales_points,
        /** @var array<CashlessProductSummaryDTO> */
        public array $top_products,
    ) {}
}
