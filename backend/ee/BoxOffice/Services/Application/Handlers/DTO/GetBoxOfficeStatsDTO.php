<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class GetBoxOfficeStatsDTO extends BaseDataObject
{
    public function __construct(
        public int $event_id,
        public int $box_office_id,
        public ?string $from = null,
        public ?string $to = null,
    ) {}
}
