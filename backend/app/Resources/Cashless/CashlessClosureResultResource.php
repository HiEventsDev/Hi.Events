<?php

declare(strict_types=1);

namespace HiEvents\Resources\Cashless;

use HiEvents\Resources\BaseResource;
use HiEvents\Services\Domain\Cashless\DTO\CashlessClosureResultDTO;
use Illuminate\Http\Request;

/**
 * @mixin CashlessClosureResultDTO
 */
class CashlessClosureResultResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'wallets_closed' => $this->wallets_closed,
            'amount_closed' => $this->amount_closed,
        ];
    }
}
