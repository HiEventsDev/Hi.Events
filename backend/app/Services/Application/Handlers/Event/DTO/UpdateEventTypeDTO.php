<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Event\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\EventType;

class UpdateEventTypeDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $event_id,
        public readonly int $account_id,
        public readonly EventType $type,
    ) {}
}
