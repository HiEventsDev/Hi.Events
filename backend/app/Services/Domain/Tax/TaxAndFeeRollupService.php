<?php

namespace HiEvents\Services\Domain\Tax;

namespace HiEvents\Services\Domain\Tax;

use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use Illuminate\Support\Str;

class TaxAndFeeRollupService
{
    private array $rollUp = [];

    public function getRollUp(): array
    {
        return $this->rollUp;
    }

    public function resetRollUp(): void
    {
        $this->rollUp = [];
    }

    public function getTotalTaxes(): float
    {
        return collect($this->rollUp['taxes'] ?? [])->sum('value');
    }

    public function getTotalFees(): float
    {
        return collect($this->rollUp['fees'] ?? [])->sum('value');
    }

    public function getTotalTaxesAndFees(): float
    {
        return $this->getTotalTaxes() + $this->getTotalFees();
    }

    public function addToRollUp(TaxAndFeesDomainObject $taxOrFee, float $amount): void
    {
        $type = strtolower(Str::plural($taxOrFee->getType()));
        $name = $taxOrFee->getName();

        $this->rollUp[$type] ??= [];

        $foundIndex = array_search($name, array_column($this->rollUp[$type], 'name'), true);
        if ($foundIndex === false) {
            $entry = [
                'name' => $name,
                'rate' => $taxOrFee->getRate(),
                'type' => $taxOrFee->getCalculationType(),
                'value' => $amount,
            ];

            if ($taxOrFee->getIsOnlineOnly()) {
                $entry['is_online_only'] = true;
            }

            if ($taxOrFee->getIsTaxInclusive()) {
                $entry['is_tax_inclusive'] = true;
            }

            $this->rollUp[$type][] = $entry;
        } else {
            $this->rollUp[$type][$foundIndex]['value'] += $amount;
        }
    }
}
