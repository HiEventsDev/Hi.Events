<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertSeatMapDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $organizer_id,
        public readonly int $account_id,
        public readonly string $name,
        public readonly array $layout,
        public readonly ?int $version = null,
    ) {}
}
