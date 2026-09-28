<?php

declare(strict_types=1);

namespace HiEvents\Resources\Cashless;

use HiEvents\Resources\BaseResource;
use HiEvents\Services\Domain\Cashless\DTO\CashlessProductSummaryDTO;
use HiEvents\Services\Domain\Cashless\DTO\CashlessSalesPointSummaryDTO;
use HiEvents\Services\Domain\Cashless\DTO\CashlessSummaryDTO;
use Illuminate\Http\Request;

/**
 * @mixin CashlessSummaryDTO
 */
class CashlessSummaryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'wallets_total' => $this->wallets_total,
            'wallets_with_balance' => $this->wallets_with_balance,
            'wallets_frozen' => $this->wallets_frozen,
            'wallets_closed' => $this->wallets_closed,
            'outstanding_balance' => $this->outstanding_balance,
            'topped_up_online' => $this->topped_up_online,
            'topped_up_staff' => $this->topped_up_staff,
            'spent' => $this->spent,
            'refunded' => $this->refunded,
            'closed' => $this->closed,
            'purchases_count' => $this->purchases_count,
            'sales_points' => array_map(static fn (CashlessSalesPointSummaryDTO $salesPoint) => [
                'name' => $salesPoint->name,
                'spent' => $salesPoint->spent,
                'purchases_count' => $salesPoint->purchases_count,
                'topped_up' => $salesPoint->topped_up,
            ], $this->sales_points),
            'top_products' => array_map(static fn (CashlessProductSummaryDTO $product) => [
                'title' => $product->title,
                'quantity' => $product->quantity,
                'total' => $product->total,
            ], $this->top_products),
        ];
    }
}
