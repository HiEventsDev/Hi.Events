<?php

namespace HiEvents\Services\Domain\Auth\TwoFactor\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class TwoFactorChallengeDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $userId,
        public readonly bool $verified,
        public readonly ?int $recoveryCodesRemaining = null,
    ) {}
}
