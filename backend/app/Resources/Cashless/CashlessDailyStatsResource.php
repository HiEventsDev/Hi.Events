<?php

declare(strict_types=1);

namespace HiEvents\Resources\Cashless;

use HiEvents\Resources\BaseResource;
use HiEvents\Services\Domain\Cashless\DTO\CashlessDailyStatsDTO;
use Illuminate\Http\Request;

/**
 * @mixin CashlessDailyStatsDTO
 */
class CashlessDailyStatsResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->date,
            'topped_up' => $this->topped_up,
            'spent' => $this->spent,
            'refunded' => $this->refunded,
        ];
    }
}
