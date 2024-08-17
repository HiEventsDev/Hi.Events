<?php

namespace HiEvents\Services\Handlers\CheckInList\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class UpsertCheckInListDTO extends BaseDTO
{
    public function __construct(
        public string  $name,
        public ?string $description,
        public int     $eventId,
        public array   $ticketIds,
        public ?string $expiresAt = null,
        public ?string $activatesAt = null,
        public ?int    $id = null,
    )
    {
    }
}
