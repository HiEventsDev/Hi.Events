<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class UpsertCheckInListDTO extends BaseDTO
{
    public function __construct(
        public string  $name,
        public ?string $description,
        public int     $eventId,
        public array   $productIds,
        public ?string $expiresAt = null,
        public ?string $activatesAt = null,
        public ?int    $id = null,
    )
    {
    }
}
