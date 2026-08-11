<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Export;

class SpreadsheetFormulaEscaper
{
    private const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    public function isFormulaTrigger(mixed $value): bool
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return false;
        }

        return in_array($value[0], self::FORMULA_TRIGGERS, true);
    }

    public function escape(mixed $value): mixed
    {
        return $this->isFormulaTrigger($value) ? "'".$value : $value;
    }

    /**
     * @param  array<int|string, mixed>  $row
     * @return array<int|string, mixed>
     */
    public function escapeRow(array $row): array
    {
        return array_map(fn (mixed $value) => $this->escape($value), $row);
    }
}
