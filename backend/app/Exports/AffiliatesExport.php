<?php

namespace HiEvents\Exports;

use Carbon\Carbon;
use HiEvents\DomainObjects\AffiliateDomainObject;
use HiEvents\Resources\Affiliate\AffiliateResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AffiliatesExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    private LengthAwarePaginator $affiliates;

    public function withData(LengthAwarePaginator $affiliates): AffiliatesExport
    {
        $this->affiliates = $affiliates;
        return $this;
    }

    public function collection(): AnonymousResourceCollection
    {
        return AffiliateResource::collection($this->affiliates);
    }

    public function headings(): array
    {
        return [
            __('ID'),
            __('Name'),
            __('Code'),
            __('Email'),
            __('Total Sales'),
            __('Total Sales Gross'),
            __('Status'),
            __('Created At'),
            __('Updated At'),
        ];
    }

    /**
     * @param AffiliateDomainObject $affiliate
     * @return array
     */
    public function map($affiliate): array
    {
        return [
            $affiliate->getId(),
            $affiliate->getName(),
            $affiliate->getCode(),
            $affiliate->getEmail(),
            $affiliate->getTotalSales(),
            $affiliate->getTotalSalesGross(),
            $affiliate->getStatus(),
            Carbon::parse($affiliate->getCreatedAt())->format('Y-m-d H:i:s'),
            Carbon::parse($affiliate->getUpdatedAt())->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}