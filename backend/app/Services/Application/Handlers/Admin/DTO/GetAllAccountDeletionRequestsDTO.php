<?php

namespace HiEvents\Services\Application\Handlers\Admin\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class GetAllAccountDeletionRequestsDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $perPage = 20,
        public readonly ?string $search = null,
        public readonly ?string $status = null,
    ) {}
}
