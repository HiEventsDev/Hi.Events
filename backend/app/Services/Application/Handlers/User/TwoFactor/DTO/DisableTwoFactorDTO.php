<?php

namespace HiEvents\Services\Application\Handlers\User\TwoFactor\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class DisableTwoFactorDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $userId,
        public readonly string $password,
        public readonly ?string $code,
        public readonly ?string $recoveryCode,
    ) {}
}
