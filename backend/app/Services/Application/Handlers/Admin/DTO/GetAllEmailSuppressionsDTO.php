<?php

namespace HiEvents\Services\Application\Handlers\Admin\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class GetAllEmailSuppressionsDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $perPage = 20,
        public readonly ?string $search = null,
        public readonly ?string $reason = null,
        public readonly ?string $source = null,
        public readonly ?string $sortBy = 'created_at',
        public readonly ?string $sortDirection = 'desc',
    ) {
    }
}
