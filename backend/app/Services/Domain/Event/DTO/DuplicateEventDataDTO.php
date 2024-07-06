<?php

namespace HiEvents\Services\Domain\Event\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class DuplicateEventDataDTO extends BaseDTO
{
    public function __construct(
        public int     $eventId,
        public int     $accountId,
        public string  $title,
        public string  $startDate,
        public bool    $duplicateTickets = true,
        public bool    $duplicateQuestions = true,
        public bool    $duplicateSettings = true,
        public bool    $duplicatePromoCodes = true,
        public ?string $description = null,
        public ?string $endDate = null,
    )
    {
    }
}
