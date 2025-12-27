<?php

namespace HiEvents\Services\Domain\Report\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use Illuminate\Support\Collection;

class PaginatedReportDTO extends BaseDataObject
{
    public function __construct(
        public readonly Collection $data,
        public readonly int        $total,
        public readonly int        $page,
        public readonly int        $perPage,
        public readonly int        $lastPage,
    )
    {
    }

    public function toArray(): array
    {
        return [
            'data' => $this->data->toArray(),
            'pagination' => [
                'total' => $this->total,
                'page' => $this->page,
                'per_page' => $this->perPage,
                'last_page' => $this->lastPage,
            ],
        ];
    }
}
