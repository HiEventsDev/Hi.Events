<?php

namespace HiEvents\Services\Domain\Auth\Access;

class FinancialStatsRedactor
{
    public const FINANCIAL_KEYS = [
        'total_gross_sales',
        'total_sales_gross',
        'total_fees',
        'total_tax',
        'total_refunded',
    ];

    public function redact(mixed $stats): array
    {
        return $this->strip(json_decode(json_encode($stats), true));
    }

    private function strip(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, self::FINANCIAL_KEYS, true)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = $this->strip($value);
            }
        }

        return $data;
    }
}
