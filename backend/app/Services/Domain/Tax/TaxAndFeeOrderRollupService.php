<?php

namespace HiEvents\Services\Domain\Tax;

use Illuminate\Support\Collection;

class TaxAndFeeOrderRollupService
{
    public function rollup(Collection $orderItems): array
    {
        $orderRollup = [];

        foreach ($orderItems as $orderItem) {
            $itemTaxRollUp = $orderItem->getTaxesAndFeesRollup();

            foreach ($itemTaxRollUp as $type => $taxesAndFees) {
                $orderRollup[$type] ??= [];

                foreach ($taxesAndFees as $taxOrFee) {
                    $foundIndex = array_search($taxOrFee['name'], array_column($orderRollup[$type], 'name'), true);
                    if ($foundIndex === false) {
                        $orderRollup[$type][] = [
                            'name' => $taxOrFee['name'],
                            'value' => $taxOrFee['value'],
                            'rate' => $taxOrFee['rate'],
                            'type' => $taxOrFee['type'],
                        ];
                    } else {
                        $orderRollup[$type][$foundIndex]['value'] += $taxOrFee['value'];
                    }
                }
            }
        }

        return $orderRollup;
    }
}
