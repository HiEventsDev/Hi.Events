<?php

namespace HiEvents\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use HiEvents\Resources\PromoCode\PromoCodeResource;

class PromoCodesExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    private $data;

    public function withData($data): PromoCodesExport
    {
        $this->data = $data;
        return $this;
    }

    public function collection()
    {
        return PromoCodeResource::collection($this->data);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Code',
            'Discount',
            'Discount Type',
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
