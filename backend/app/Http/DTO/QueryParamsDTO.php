<?php

namespace HiEvents\Http\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class QueryParamsDTO extends BaseDTO
{
    public function __construct(
        public readonly ?int    $page = 1,
        public readonly ?int    $per_page = 25,
        public readonly ?string $sort_by = 'id',
        public readonly ?string $sort_direction = 'desc',
        public readonly ?string $query = null,
        public readonly ?array  $filter_fields = null,
        public readonly ?array  $includes = null,
    )
    {
    }
}
