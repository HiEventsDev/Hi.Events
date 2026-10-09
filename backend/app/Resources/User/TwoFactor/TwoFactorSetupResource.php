<?php

namespace HiEvents\Resources\User\TwoFactor;

use HiEvents\Services\Domain\Auth\TwoFactor\DTO\TwoFactorSetupDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TwoFactorSetupDTO
 */
class TwoFactorSetupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'secret' => $this->secret,
            'otpauth_uri' => $this->otpauthUri,
        ];
    }
}
