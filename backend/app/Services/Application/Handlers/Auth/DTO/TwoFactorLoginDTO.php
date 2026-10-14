<?php

namespace HiEvents\Services\Application\Handlers\Auth\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class TwoFactorLoginDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $challengeToken,
        public readonly ?string $code,
        public readonly ?string $recoveryCode,
        public readonly ?int $accountId,
        public readonly bool $rememberDevice,
        public readonly ?string $userAgent,
        public readonly ?string $ipAddress,
    ) {}
}
