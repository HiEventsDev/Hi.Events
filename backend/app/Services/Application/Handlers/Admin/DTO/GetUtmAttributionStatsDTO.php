<?php

namespace HiEvents\Services\Application\Handlers\Admin\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

final class GetUtmAttributionStatsDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $group_by = 'source',
        public readonly ?string $date_from = null,
        public readonly ?string $date_to = null,
        public readonly int $per_page = 20,
        public readonly int $page = 1,
    )
    {
    }
}
