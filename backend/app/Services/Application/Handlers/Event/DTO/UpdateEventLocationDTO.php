<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Event\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Services\Domain\EventLocation\EventLocationData;

class UpdateEventLocationDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $event_id,
        public readonly int $account_id,
        public readonly ?EventLocationData $event_location = null,
        public readonly bool $clear_event_location = false,
    ) {}
}
