<?php

namespace HiEvents\Services\Domain\CheckInList\DTO;

class CheckInResultDTO
{
    public function __construct(
        public readonly ?object $checkIn = null,
        public readonly ?string $error = null,
    )
    {
    }
}
