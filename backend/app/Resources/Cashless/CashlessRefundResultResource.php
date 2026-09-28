<?php

declare(strict_types=1);

namespace HiEvents\Resources\Cashless;

use HiEvents\Resources\BaseResource;
use HiEvents\Services\Application\Handlers\Cashless\DTO\RefundCashlessWalletResultDTO;
use Illuminate\Http\Request;

/**
 * @mixin RefundCashlessWalletResultDTO
 */
class CashlessRefundResultResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'refunded_amount' => $this->refunded_amount,
            'unrefundable_amount' => $this->unrefundable_amount,
        ];
    }
}
