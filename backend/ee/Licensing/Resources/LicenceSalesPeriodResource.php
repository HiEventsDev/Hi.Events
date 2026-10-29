<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing\Resources;

use HiEvents\Enterprise\Licensing\DTO\LicenceSalesPeriodDTO;
use HiEvents\Repository\DTO\SalesByCurrencyDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LicenceSalesPeriodDTO
 */
class LicenceSalesPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'starts_on' => $this->starts_at->toDateString(),
            'ends_on' => $this->ends_at->subDay()->toDateString(),
            'is_current' => $this->is_current,
            'sales' => $this->sales->map(static fn (SalesByCurrencyDTO $sales) => [
                'currency' => $sales->currency,
                'amount' => $sales->amount,
            ])->values()->all(),
        ];
    }
}
