<?php

declare(strict_types=1);

namespace HiEvents\Exports\ValueBinders;

use HiEvents\Services\Infrastructure\Export\SpreadsheetFormulaEscaper;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class FormulaSafeValueBinder extends DefaultValueBinder
{
    private ?SpreadsheetFormulaEscaper $escaper = null;

    public function bindValue(Cell $cell, mixed $value): bool
    {
        $this->escaper ??= app(SpreadsheetFormulaEscaper::class);

        if ($this->escaper->isFormulaTrigger($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
