<?php

namespace HiEvents\Services\Domain\SelfService\DTO;

class EditAttendeeResultDTO
{
    public function __construct(
        public readonly bool $success,
        public readonly bool $shortIdChanged,
        public readonly ?string $newShortId,
        public readonly bool $emailChanged,
    ) {}
}
