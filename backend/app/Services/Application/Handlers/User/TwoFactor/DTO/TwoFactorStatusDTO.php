<?php

namespace HiEvents\Services\Application\Handlers\User\TwoFactor\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use Illuminate\Support\Collection;

class TwoFactorStatusDTO extends BaseDataObject
{
    public function __construct(
        public readonly bool $enabled,
        public readonly ?string $confirmedAt,
        public readonly int $recoveryCodesRemaining,
        public readonly Collection $trustedDevices,
        public readonly Collection $requiredByAccounts,
    ) {}
}
