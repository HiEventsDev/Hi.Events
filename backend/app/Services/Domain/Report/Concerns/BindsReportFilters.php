<?php

namespace HiEvents\Services\Domain\Report\Concerns;

use Illuminate\Support\Carbon;

trait BindsReportFilters
{
    /**
     * @return array{start_at: string, end_at: string}
     */
    protected function utcRangeBindings(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'start_at' => $startDate->copy()->utc()->format('Y-m-d H:i:s.u'),
            'end_at' => $endDate->copy()->utc()->format('Y-m-d H:i:s.u'),
        ];
    }

    protected function buildCurrencyFilter(string $column, ?string $currency): string
    {
        return $currency === null ? '' : "AND $column = :currency";
    }

    /**
     * @return array{currency?: string}
     */
    protected function currencyBinding(?string $currency): array
    {
        return $currency === null ? [] : ['currency' => $currency];
    }
}
