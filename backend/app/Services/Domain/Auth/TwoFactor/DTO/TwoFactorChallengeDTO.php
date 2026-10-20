<?php

namespace HiEvents\Services\Domain\Auth\TwoFactor\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Services\Domain\Auth\Sso\DTO\AuthContext;

class TwoFactorChallengeDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $userId,
        public readonly bool $verified,
        public readonly ?int $recoveryCodesRemaining = null,
        public readonly ?AuthContext $authContext = null,
    ) {}
}
