<?php

namespace HiEvents\Resources\Auth;

use HiEvents\Services\Domain\Auth\DTO\LoginResponse;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorChallengeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoginResponse
 */
class TwoFactorChallengeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'token' => null,
            'two_factor_required' => true,
            'two_factor_challenge_token' => $this->twoFactorChallengeToken,
            'expires_in' => TwoFactorChallengeService::TTL_SECONDS,
        ];
    }
}
