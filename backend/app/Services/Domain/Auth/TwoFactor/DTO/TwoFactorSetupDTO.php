<?php

namespace HiEvents\Services\Domain\Auth\TwoFactor\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class TwoFactorSetupDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $secret,
        public readonly string $otpauthUri,
    ) {}
}
