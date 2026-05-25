<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Organizer\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpdateOrganizerLocationDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $organizer_id,
        public readonly int $account_id,
        public readonly ?int $location_id = null,
    ) {}
}
