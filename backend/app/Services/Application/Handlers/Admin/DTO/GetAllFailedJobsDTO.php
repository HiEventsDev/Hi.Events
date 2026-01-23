<?php

namespace HiEvents\Services\Application\Handlers\Admin\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class GetAllFailedJobsDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $perPage = 20,
        public readonly ?string $search = null,
        public readonly ?string $queue = null,
        public readonly ?string $sortBy = 'failed_at',
        public readonly ?string $sortDirection = 'desc',
    ) {
    }
}
