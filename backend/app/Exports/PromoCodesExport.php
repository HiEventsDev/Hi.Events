<?php

namespace HiEvents\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PromoCodesExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    private $data;

    public function withData($data): PromoCodesExport
    {
        $this->data = $data;

        return $this;
    }

    public function collection(): Collection
    {
        return $this->data instanceof Collection
            ? $this->data
            : collect(is_array($this->data) ? $this->data : $this->data->items());
    }

    public function headings(): array
    {
        return [
            'ID',
            'Code',
            'Discount',
            'Discount Type',
            'Discount Applies To',
            'Max Allowed Uses',
            'Expiry Date',
            'Event ID',
            'Created At',
            'Updated At',
        ];
    }

    public function map($discountCode): array
    {
        return [
            $discountCode->getId(),
            $discountCode->getCode(),
            $discountCode->getDiscount(),
            $discountCode->getDiscountType(),
            $discountCode->getDiscountAppliesTo(),
            $discountCode->getMaxAllowedUsages(),
            $discountCode->getExpiryDate(),
            $discountCode->getEventId(),
            $discountCode->getCreatedAt(),
            $discountCode->getUpdatedAt(),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
