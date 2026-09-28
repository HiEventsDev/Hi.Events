<?php

declare(strict_types=1);

namespace HiEvents\Resources\Cashless;

use HiEvents\Resources\BaseResource;
use HiEvents\Services\Domain\Cashless\DTO\CashlessQuoteDTO;
use Illuminate\Http\Request;

/**
 * @mixin CashlessQuoteDTO
 */
class CashlessQuoteResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'subtotal' => $this->subtotal,
            'fees' => $this->fees,
            'taxes' => $this->taxes,
            'total' => $this->total,
        ];
    }
}
