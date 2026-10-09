<?php

namespace HiEvents\Resources\User\TwoFactor;

use HiEvents\Services\Application\Handlers\User\TwoFactor\DTO\TwoFactorStatusDTO;
use HiEvents\Services\Domain\Auth\TwoFactor\RecoveryCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin TwoFactorStatusDTO
 */
class TwoFactorStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'enabled' => $this->enabled,
            'confirmed_at' => $this->confirmedAt !== null ? Carbon::parse($this->confirmedAt)->toIso8601String() : null,
            'recovery_codes_remaining' => $this->recoveryCodesRemaining,
            'recovery_codes_total' => RecoveryCodeService::CODE_COUNT,
            'trusted_devices' => TrustedDeviceResource::collection($this->trustedDevices),
            /** @var array<int, string> */
            'required_by_accounts' => $this->requiredByAccounts->all(),
        ];
    }
}
